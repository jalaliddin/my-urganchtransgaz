<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import IssueExecutorPicker from '@/components/issues/IssueExecutorPicker.vue'
import LeafletMap, { type MapMarker } from '@/components/issues/LeafletMap.vue'
import { issueService } from '@/services/issueService'
import { useAuthStore } from '@/stores/auth'
import type { Issue, IssueCategory, IssueOptions } from '@/types/models'
import { formatDate } from '@/utils/date'
import { issueLegend, issuePinStyle, openDays } from '@/utils/issueMap'
import { PHOTO_ACCEPT, uploadErrorMessage } from '@/utils/uploads'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const canCreate = computed(() => auth.can('issues.create'))
// Only Technical Policy Service names executors; everyone else just reports.
const canAssign = computed(() => auth.can('issues.assign'))

// The map needs every issue at once, not a page of them — capped so a huge
// history can't flood the browser; the notice below says when it's cut.
const MAP_LIMIT = 300

const issues = ref<Issue[]>([])
const totalIssues = ref(0)
const loading = ref(false)
// "Open" first — this is the view that matters for staying on top of
// what's unresolved; "All" is there for history/context, not the default.
const statusFilter = ref<'open' | 'all'>('open')
const categoryFilter = ref<number | null>(null)
const searchFilter = ref('')
const unassignedOnly = ref(false)
const categories = ref<IssueCategory[]>([])
const mapRef = ref<InstanceType<typeof LeafletMap>>()

async function load() {
  loading.value = true
  try {
    const result = await issueService.list({
      per_page: MAP_LIMIT,
      ...(statusFilter.value === 'open' ? { 'filter[status]': 'open' } : {}),
      ...(categoryFilter.value ? { 'filter[issue_category_id]': categoryFilter.value } : {}),
      ...(searchFilter.value ? { 'filter[search]': searchFilter.value } : {}),
      ...(unassignedOnly.value ? { 'filter[unassigned]': 1 } : {}),
    })
    issues.value = result.data
    totalIssues.value = result.meta.total
  } finally {
    loading.value = false
  }
}

let searchDebounce: ReturnType<typeof setTimeout> | undefined
function onSearch(value: string | null) {
  searchFilter.value = value ?? ''
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(load, 350)
}

function showOnMap(id: number) {
  mapRef.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  mapRef.value?.focusMarker(id)
}

onMounted(async () => {
  await load()
  // Categories feed the list filter for anyone who can see issues, not
  // only those who can report them — hence not gated on `canCreate`.
  if (canCreate.value) {
    categories.value = (await issueService.options()).categories
  }
})

const markers = computed<MapMarker[]>(() =>
  issues.value.map((issue) => {
    const daysOpen = issue.status === 'open' ? openDays(issue.created_at) : null
    return {
      id: issue.id,
      lat: issue.latitude,
      lng: issue.longitude,
      title: issue.title,
      status: issue.status,
      ...issuePinStyle(issue.status, daysOpen),
      details: [
        issue.category?.name,
        issue.organization?.name,
        issue.object_name,
        issue.executors?.length ? null : t('issues.noExecutors'),
        `${t('issues.reportedAt')}: ${formatDate(issue.created_at)}`,
        daysOpen !== null ? t('issues.openDays', { n: daysOpen }) : `${t('status.resolved')}: ${formatDate(issue.resolved_at)}`,
      ].filter((line): line is string => !!line),
    }
  }),
)

const legend = computed(() => issueLegend(t))

const headers = computed(() => [
  { title: t('issues.issueTitle'), key: 'title' },
  { title: t('issues.category'), key: 'category' },
  { title: t('issues.organization'), key: 'organization' },
  { title: t('issues.executors'), key: 'executors', sortable: false },
  { title: t('issues.reportedAt'), key: 'created_at' },
  { title: t('issues.status'), key: 'status' },
  { title: '', key: 'locate', sortable: false, align: 'end' as const, width: 56 },
])

function openIssue(id: number) {
  router.push({ name: 'issue-detail', params: { id } })
}

// Create dialog — location comes from a map click, not typed coordinates.
const dialogOpen = ref(false)
const form = ref({
  title: '',
  description: '',
  object_name: '',
  organization_id: null as number | null,
  issue_category_id: null as number | null,
  executor_ids: [] as number[],
  photos: [] as File[],
})
const options = ref<IssueOptions | null>(null)
const pickedLocation = ref<{ lat: number; lng: number } | null>(null)
const formErrors = ref<Record<string, string[]>>({})
const saveError = ref<string | null>(null)
const saving = ref(false)
// Laravel reports each photo separately (photos.0, photos.1, ...).
const photoErrors = computed(() =>
  Object.entries(formErrors.value)
    .filter(([key]) => key === 'photos' || key.startsWith('photos.'))
    .flatMap(([, messages]) => messages),
)

async function openCreate() {
  form.value = {
    title: '',
    description: '',
    object_name: '',
    organization_id: null,
    issue_category_id: null,
    executor_ids: [],
    photos: [],
  }
  pickedLocation.value = null
  formErrors.value = {}
  saveError.value = null
  dialogOpen.value = true

  options.value = await issueService.options()
  categories.value = options.value.categories

  // Someone who can only file against their own organization has exactly
  // one to pick from — preselect it rather than making them choose from a
  // list of one.
  if (options.value.organizations.length === 1) {
    form.value.organization_id = options.value.organizations[0].id
  }
}

async function save() {
  if (!pickedLocation.value) {
    formErrors.value = { latitude: [t('issues.mapPickHint')] }
    return
  }

  saving.value = true
  formErrors.value = {}
  saveError.value = null
  try {
    await issueService.create({
      title: form.value.title,
      description: form.value.description || null,
      object_name: form.value.object_name || null,
      organization_id: form.value.organization_id,
      issue_category_id: form.value.issue_category_id as number,
      ...(options.value?.can_assign_executors && form.value.executor_ids.length ? { executor_ids: form.value.executor_ids } : {}),
      latitude: pickedLocation.value.lat,
      longitude: pickedLocation.value.lng,
      photos: form.value.photos,
    })
    dialogOpen.value = false
    await load()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
    if (!axiosError.response?.data?.errors) saveError.value = uploadErrorMessage(error, t('common.uploadFailed'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('issues.title')">
    <template #actions>
      <v-select
        v-if="categories.length"
        v-model="categoryFilter"
        :items="categories"
        item-title="name"
        item-value="id"
        :label="$t('issues.category')"
        :placeholder="$t('issues.allCategories')"
        clearable
        hide-details
        density="comfortable"
        class="mr-3 issue-filter"
        @update:model-value="load"
      />
      <v-btn-toggle v-model="statusFilter" mandatory density="comfortable" class="mr-3" @update:model-value="load">
        <v-btn value="open">{{ $t('issues.filterOpen') }}</v-btn>
        <v-btn value="all">{{ $t('issues.filterAll') }}</v-btn>
      </v-btn-toggle>
      <v-btn v-if="auth.can('issues.report')" variant="tonal" prepend-icon="mdi-chart-box-outline" class="mr-3" :to="{ name: 'issues-report' }">
        {{ $t('issues.report') }}
      </v-btn>
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">
        {{ $t('issues.reportIssue') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <v-card class="mb-4">
    <LeafletMap ref="mapRef" :markers="markers" :legend="legend" :height="420" @marker-click="openIssue" />
    <v-alert v-if="totalIssues > issues.length" type="info" variant="tonal" density="compact" rounded="0">
      {{ $t('issues.showingLimited', { shown: issues.length, total: totalIssues }) }}
    </v-alert>
  </v-card>

  <v-card>
    <v-card-text class="pb-0">
      <v-text-field
        :model-value="searchFilter"
        :placeholder="$t('issues.searchByTitle')"
        prepend-inner-icon="mdi-magnify"
        density="comfortable"
        variant="outlined"
        hide-details
        clearable
        style="max-width: 360px"
        @update:model-value="onSearch"
      />
      <v-switch
        v-if="canAssign"
        v-model="unassignedOnly"
        :label="$t('issues.unassignedOnly')"
        color="primary"
        density="compact"
        hide-details
        inset
        class="mt-2"
        @update:model-value="load"
      />
    </v-card-text>
    <v-data-table :headers="headers" :items="issues" :loading="loading" item-value="id">
      <template #item.title="{ item }">
        <a href="#" class="text-decoration-none" @click.prevent="openIssue(item.id)">{{ item.title }}</a>
      </template>
      <template #item.category="{ item }">{{ item.category?.name ?? '—' }}</template>
      <template #item.organization="{ item }">{{ item.organization?.name ?? '—' }}</template>
      <template #item.executors="{ item }">
        <v-chip v-if="!item.executors?.length" size="small" color="warning" variant="tonal" label>{{ $t('issues.noExecutors') }}</v-chip>
        <template v-else>
          {{ item.executors[0].full_name }}
          <v-chip v-if="item.executors.length > 1" size="x-small" class="ml-1" variant="tonal">+{{ item.executors.length - 1 }}</v-chip>
        </template>
      </template>
      <template #item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>
      <template #item.status="{ item }">
        <AppStatusChip :status="item.status" />
      </template>
      <template #item.locate="{ item }">
        <v-btn
          icon="mdi-map-marker-radius-outline"
          size="small"
          variant="text"
          :title="$t('issues.showOnMap')"
          :aria-label="`${$t('issues.showOnMap')}: ${item.title}`"
          @click="showOnMap(item.id)"
        />
      </template>
      <template #no-data>
        <AppEmptyState icon="mdi-map-marker-alert-outline" :message="$t('issues.noIssues')" />
      </template>
    </v-data-table>
  </v-card>

  <v-dialog v-model="dialogOpen" max-width="560">
    <v-card>
      <v-card-title>{{ $t('issues.reportIssue') }}</v-card-title>
      <v-card-text>
        <v-select
          v-model="form.organization_id"
          :items="options?.organizations ?? []"
          item-title="name"
          item-value="id"
          :label="$t('issues.organization')"
          :error-messages="formErrors.organization_id"
        />
        <v-select
          v-model="form.issue_category_id"
          :items="options?.categories ?? []"
          item-title="name"
          item-value="id"
          :label="$t('issues.category')"
          :error-messages="formErrors.issue_category_id"
        />
        <IssueExecutorPicker
          v-if="options?.can_assign_executors"
          v-model="form.executor_ids"
          :organization-id="form.organization_id"
          :error-messages="formErrors.executor_ids"
          class="mb-2"
        />
        <v-alert v-else-if="options" type="info" variant="tonal" density="compact" class="mb-4">
          {{ $t('issues.executorsAssignedLater') }}
        </v-alert>
        <v-text-field v-model="form.title" :label="$t('issues.issueTitle')" :error-messages="formErrors.title" />
        <v-textarea v-model="form.description" :label="$t('issues.description')" rows="2" :error-messages="formErrors.description" />
        <v-text-field v-model="form.object_name" :label="$t('issues.objectName')" :error-messages="formErrors.object_name" />
        <div class="text-body-2 text-medium-emphasis mb-2">{{ $t('issues.mapPickHint') }}</div>
        <LeafletMap pickable :picked-location="pickedLocation" :height="260" @pick="(loc) => (pickedLocation = loc)" />
        <div v-if="formErrors.latitude" class="text-error text-caption mt-1">{{ formErrors.latitude[0] }}</div>
        <v-file-input
          v-model="form.photos"
          class="mt-4"
          :label="$t('issues.photos')"
          :hint="$t('issues.photosHint')"
          persistent-hint
          :accept="PHOTO_ACCEPT"
          multiple
          chips
          counter
          show-size
          prepend-icon=""
          prepend-inner-icon="mdi-camera-outline"
          :error-messages="photoErrors"
          :rules="[(files: File[]) => (files?.length ?? 0) <= 5 || $t('issues.photosMax')]"
        />
        <v-alert v-if="saveError" type="error" variant="tonal" density="compact" class="mt-3">{{ saveError }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.issue-filter {
  min-width: 220px;
  max-width: 260px;
}
</style>
