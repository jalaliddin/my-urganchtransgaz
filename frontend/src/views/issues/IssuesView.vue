<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import LeafletMap, { type MapMarker } from '@/components/issues/LeafletMap.vue'
import { issueService } from '@/services/issueService'
import { useAuthStore } from '@/stores/auth'
import type { Issue } from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const canCreate = computed(() => auth.can('issues.create'))

const issues = ref<Issue[]>([])
const loading = ref(false)
// "Open" first — this is the view that matters for staying on top of
// what's unresolved; "All" is there for history/context, not the default.
const statusFilter = ref<'open' | 'all'>('open')

async function load() {
  loading.value = true
  try {
    const result = await issueService.list({
      per_page: 100,
      ...(statusFilter.value === 'open' ? { 'filter[status]': 'open' } : {}),
    })
    issues.value = result.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

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
  { title: t('issues.department'), key: 'department' },
  { title: t('issues.reporter'), key: 'reporter' },
  { title: t('issues.status'), key: 'status' },
])

function openIssue(id: number) {
  router.push({ name: 'issue-detail', params: { id } })
}

// Create dialog — location comes from a map click, not typed coordinates.
const dialogOpen = ref(false)
const form = ref({ title: '', description: '', object_name: '' })
const pickedLocation = ref<{ lat: number; lng: number } | null>(null)
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  form.value = { title: '', description: '', object_name: '' }
  pickedLocation.value = null
  formErrors.value = {}
  dialogOpen.value = true
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
      <template #item.department="{ item }">{{ item.department?.name ?? '—' }}</template>
      <template #item.reporter="{ item }">{{ item.reporter?.full_name ?? '—' }}</template>
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
