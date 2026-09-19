<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import LeafletMap, { type MapMarker } from '@/components/issues/LeafletMap.vue'
import { issueService } from '@/services/issueService'
import { useAuthStore } from '@/stores/auth'
import type { Issue, IssueCategory, IssueOptions, IssueResponsibleCandidate } from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const canCreate = computed(() => auth.can('issues.create'))

const issues = ref<Issue[]>([])
const loading = ref(false)
// "Open" first — this is the view that matters for staying on top of
// what's unresolved; "All" is there for history/context, not the default.
const statusFilter = ref<'open' | 'all'>('open')
const categoryFilter = ref<number | null>(null)
const categories = ref<IssueCategory[]>([])

async function load() {
  loading.value = true
  try {
    const result = await issueService.list({
      per_page: 100,
      ...(statusFilter.value === 'open' ? { 'filter[status]': 'open' } : {}),
      ...(categoryFilter.value ? { 'filter[issue_category_id]': categoryFilter.value } : {}),
    })
    issues.value = result.data
  } finally {
    loading.value = false
  }
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
  issues.value.map((issue) => ({
    id: issue.id,
    lat: issue.latitude,
    lng: issue.longitude,
    title: issue.title,
    status: issue.status,
  })),
)

const headers = computed(() => [
  { title: t('issues.issueTitle'), key: 'title' },
  { title: t('issues.category'), key: 'category' },
  { title: t('issues.organization'), key: 'organization' },
  { title: t('issues.responsible'), key: 'responsible' },
  { title: t('issues.status'), key: 'status' },
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
  responsible_employee_id: null as number | null,
})
const options = ref<IssueOptions | null>(null)
const candidates = ref<IssueResponsibleCandidate[]>([])
const loadingCandidates = ref(false)
const pickedLocation = ref<{ lat: number; lng: number } | null>(null)
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

async function openCreate() {
  form.value = {
    title: '',
    description: '',
    object_name: '',
    organization_id: null,
    issue_category_id: null,
    responsible_employee_id: null,
  }
  pickedLocation.value = null
  candidates.value = []
  formErrors.value = {}
  dialogOpen.value = true

  options.value = await issueService.options()
  categories.value = options.value.categories

  // A department-manager only ever has their own organization to pick from
  // — preselect it rather than making them choose from a list of one.
  if (options.value.organizations.length === 1) {
    form.value.organization_id = options.value.organizations[0].id
    await loadCandidates()
  }
}

// The responsible employee must belong to the chosen organization, so the
// candidate list follows the organization select and the old choice is
// dropped whenever it changes.
async function loadCandidates() {
  form.value.responsible_employee_id = null
  candidates.value = []

  if (!options.value?.must_choose_responsible || !form.value.organization_id) return

  loadingCandidates.value = true
  try {
    candidates.value = await issueService.responsibleCandidates(form.value.organization_id)
  } finally {
    loadingCandidates.value = false
  }
}

async function save() {
  if (!pickedLocation.value) {
    formErrors.value = { latitude: [t('issues.mapPickHint')] }
    return
  }

  saving.value = true
  formErrors.value = {}
  try {
    await issueService.create({
      title: form.value.title,
      description: form.value.description || null,
      object_name: form.value.object_name || null,
      organization_id: form.value.organization_id,
      issue_category_id: form.value.issue_category_id as number,
      responsible_employee_id: form.value.responsible_employee_id,
      latitude: pickedLocation.value.lat,
      longitude: pickedLocation.value.lng,
    })
    dialogOpen.value = false
    await load()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
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
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">
        {{ $t('issues.reportIssue') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <v-card class="mb-4">
    <LeafletMap :markers="markers" @marker-click="openIssue" />
  </v-card>

  <v-card>
    <v-data-table :headers="headers" :items="issues" :loading="loading" item-value="id">
      <template #item.title="{ item }">
        <a href="#" class="text-decoration-none" @click.prevent="openIssue(item.id)">{{ item.title }}</a>
      </template>
      <template #item.category="{ item }">{{ item.category?.name ?? '—' }}</template>
      <template #item.organization="{ item }">{{ item.organization?.name ?? '—' }}</template>
      <template #item.responsible="{ item }">{{ item.responsible?.full_name ?? '—' }}</template>
      <template #item.status="{ item }">
        <AppStatusChip :status="item.status" />
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
          @update:model-value="loadCandidates"
        />
        <v-select
          v-model="form.issue_category_id"
          :items="options?.categories ?? []"
          item-title="name"
          item-value="id"
          :label="$t('issues.category')"
          :error-messages="formErrors.issue_category_id"
        />
        <v-select
          v-if="options?.must_choose_responsible"
          v-model="form.responsible_employee_id"
          :items="candidates"
          item-title="full_name"
          item-value="id"
          :label="$t('issues.responsible')"
          :loading="loadingCandidates"
          :disabled="!form.organization_id"
          :hint="!form.organization_id ? $t('issues.pickOrganizationFirst') : candidates.length || loadingCandidates ? '' : $t('issues.noCandidates')"
          persistent-hint
          :error-messages="formErrors.responsible_employee_id"
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props" :title="item.full_name" :subtitle="[item.position, item.department].filter(Boolean).join(' · ')" />
          </template>
        </v-select>
        <v-text-field v-else :model-value="auth.user?.name" :label="$t('issues.responsible')" :hint="$t('issues.responsibleSelf')" persistent-hint readonly />
        <v-text-field v-model="form.title" :label="$t('issues.issueTitle')" :error-messages="formErrors.title" />
        <v-textarea v-model="form.description" :label="$t('issues.description')" rows="2" :error-messages="formErrors.description" />
        <v-text-field v-model="form.object_name" :label="$t('issues.objectName')" :error-messages="formErrors.object_name" />
        <div class="text-body-2 text-medium-emphasis mb-2">{{ $t('issues.mapPickHint') }}</div>
        <LeafletMap pickable :picked-location="pickedLocation" :height="260" @pick="(loc) => (pickedLocation = loc)" />
        <div v-if="formErrors.latitude" class="text-error text-caption mt-1">{{ formErrors.latitude[0] }}</div>
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
