<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { attendanceDeviceService, type IssuedDeviceToken } from '@/services/attendanceDeviceService'
import { http } from '@/services/http'
import { organizationService } from '@/services/organizationService'
import type { AttendanceDevice, Organization } from '@/types/models'
import { formatDateTime } from '@/utils/date'

const { t } = useI18n()

const EXTENSION_ZIP = '/downloads/dahua-davomat.zip'
// What the extension posts to: this same server, as the app itself sees it.
const serverUrl = new URL(http.defaults.baseURL ?? '/api/v1', window.location.origin).toString().replace(/\/$/, '')

const devices = ref<AttendanceDevice[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    devices.value = await attendanceDeviceService.list()
  } finally {
    loading.value = false
  }
}

onMounted(load)

const headers = computed(() => [
  { title: t('attendanceDevices.deviceId'), key: 'device_id' },
  { title: t('attendanceDevices.name'), key: 'name' },
  { title: t('attendanceDevices.organization'), key: 'organization' },
  { title: t('common.status'), key: 'is_active' },
  { title: t('attendanceDevices.lastUsed'), key: 'last_used_at' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

// --- Adding / editing ------------------------------------------------------
const organizations = ref<Organization[]>([])
const dialogOpen = ref(false)
const editing = ref<AttendanceDevice | null>(null)
const form = ref({ device_id: '', name: '', organization_id: null as number | null })
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

async function openForm(device: AttendanceDevice | null) {
  editing.value = device
  form.value = { device_id: device?.device_id ?? '', name: device?.name ?? '', organization_id: device?.organization_id ?? null }
  formErrors.value = {}
  dialogOpen.value = true
  if (organizations.value.length === 0) {
    organizations.value = (await organizationService.list({ per_page: 200 })).data
  }
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    if (editing.value) {
      await attendanceDeviceService.update(editing.value.id, { name: form.value.name, organization_id: form.value.organization_id })
    } else {
      issued.value = await attendanceDeviceService.create(form.value)
    }
    dialogOpen.value = false
    await load()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

async function toggleActive(device: AttendanceDevice) {
  await attendanceDeviceService.update(device.id, { is_active: !device.is_active })
  await load()
}

// --- Tokens ------------------------------------------------------------------
const regenerateTarget = ref<AttendanceDevice | null>(null)
const regenerating = ref(false)
const issued = ref<IssuedDeviceToken | null>(null)
const copied = ref<string | null>(null)

async function confirmRegenerate() {
  if (!regenerateTarget.value) return
  regenerating.value = true
  try {
    issued.value = await attendanceDeviceService.regenerateToken(regenerateTarget.value.id)
    regenerateTarget.value = null
  } finally {
    regenerating.value = false
  }
}

async function copy(value: string, what: string) {
  try {
    await navigator.clipboard.writeText(value)
    copied.value = what
    setTimeout(() => (copied.value = null), 2000)
  } catch {
    copied.value = null
  }
}
</script>

<template>
  <AppPageHeader :title="$t('attendanceDevices.title')">
    <template #actions>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openForm(null)">{{ $t('attendanceDevices.add') }}</v-btn>
    </template>
  </AppPageHeader>

  <v-card class="mb-4">
    <v-card-title class="d-flex flex-wrap align-center ga-2">
      <v-icon icon="mdi-google-chrome" />
      {{ $t('attendanceDevices.extensionTitle') }}
    </v-card-title>
    <v-card-text>
      <p class="mb-4">{{ $t('attendanceDevices.extensionIntro') }}</p>
      <v-btn color="primary" variant="flat" prepend-icon="mdi-download" :href="EXTENSION_ZIP" download class="mb-4">
        {{ $t('attendanceDevices.download') }}
      </v-btn>

      <ol class="install-steps">
        <li>{{ $t('attendanceDevices.step1') }}</li>
        <li>
          {{ $t('attendanceDevices.step2') }}
          <span class="d-inline-flex align-center ga-1">
            <code>chrome://extensions</code>
            <v-btn
              size="x-small"
              variant="text"
              :icon="copied === 'url' ? 'mdi-check' : 'mdi-content-copy'"
              :aria-label="$t('attendanceDevices.copy')"
              @click="copy('chrome://extensions', 'url')"
            />
          </span>
        </li>
        <li>{{ $t('attendanceDevices.step3') }}</li>
        <li>{{ $t('attendanceDevices.step4') }}</li>
        <li>
          {{ $t('attendanceDevices.step5') }}
          <code>{{ serverUrl }}</code>
        </li>
      </ol>
      <v-alert type="info" variant="tonal" density="compact" class="mt-3">{{ $t('attendanceDevices.keepOpen') }}</v-alert>
    </v-card-text>
  </v-card>

  <v-card>
    <v-card-title>{{ $t('attendanceDevices.terminals') }}</v-card-title>
    <v-data-table :headers="headers" :items="devices" :loading="loading" item-value="id" hide-default-footer :items-per-page="-1">
      <template #item.device_id="{ item }">
        <code>{{ item.device_id }}</code>
      </template>
      <template #item.organization="{ item }">
        {{ item.organization?.short_name || item.organization?.name || '—' }}
      </template>
      <template #item.is_active="{ item }">
        <v-chip size="small" :color="item.is_active ? 'success' : undefined" variant="tonal" :prepend-icon="item.is_active ? 'mdi-check-circle-outline' : 'mdi-pause-circle-outline'">
          {{ item.is_active ? $t('status.active') : $t('status.inactive') }}
        </v-chip>
      </template>
      <template #item.last_used_at="{ item }">
        {{ item.last_used_at ? formatDateTime(item.last_used_at) : $t('attendanceDevices.neverUsed') }}
      </template>
      <template #item.actions="{ item }">
        <v-btn size="small" variant="text" prepend-icon="mdi-key-variant" @click="regenerateTarget = item">
          {{ $t('attendanceDevices.newToken') }}
        </v-btn>
        <v-btn icon="mdi-pencil-outline" variant="text" size="small" :aria-label="$t('common.edit')" @click="openForm(item)" />
        <v-btn
          :icon="item.is_active ? 'mdi-pause-circle-outline' : 'mdi-play-circle-outline'"
          variant="text"
          size="small"
          :aria-label="item.is_active ? $t('attendanceDevices.deactivate') : $t('attendanceDevices.activate')"
          @click="toggleActive(item)"
        />
      </template>
      <template #no-data>
        <AppEmptyState icon="mdi-gate" :message="$t('attendanceDevices.none')" />
      </template>
    </v-data-table>
  </v-card>

  <v-dialog v-model="dialogOpen" max-width="480">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('attendanceDevices.add') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-text-field
            v-model="form.device_id"
            :label="$t('attendanceDevices.deviceId')"
            :hint="$t('attendanceDevices.deviceIdHint')"
            persistent-hint
            :disabled="editing !== null"
            :error-messages="formErrors.device_id"
            class="mb-2"
            autofocus
          />
          <v-text-field v-model="form.name" :label="$t('attendanceDevices.name')" :error-messages="formErrors.name" />
          <v-autocomplete
            v-model="form.organization_id"
            :items="organizations"
            item-title="name"
            item-value="id"
            clearable
            :label="$t('attendanceDevices.organization')"
            :error-messages="formErrors.organization_id"
          />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <AppConfirmDialog
    :model-value="regenerateTarget !== null"
    :title="$t('attendanceDevices.newToken')"
    :text="$t('attendanceDevices.newTokenConfirm', { id: regenerateTarget?.device_id ?? '' })"
    :loading="regenerating"
    @update:model-value="(value: boolean) => !value && (regenerateTarget = null)"
    @confirm="confirmRegenerate"
  />

  <v-dialog :model-value="issued !== null" max-width="560" persistent>
    <v-card v-if="issued">
      <v-card-title>{{ $t('attendanceDevices.tokenTitle', { id: issued.device.device_id }) }}</v-card-title>
      <v-card-text>
        <v-alert type="warning" variant="tonal" density="compact" class="mb-4">{{ $t('attendanceDevices.tokenOnce') }}</v-alert>
        <div v-for="field in [
          { key: 'device', label: $t('attendanceDevices.deviceId'), value: issued.device.device_id },
          { key: 'token', label: $t('attendanceDevices.token'), value: issued.token },
          { key: 'server', label: $t('attendanceDevices.serverUrl'), value: serverUrl },
        ]" :key="field.key" class="mb-3">
          <div class="text-caption text-medium-emphasis">{{ field.label }}</div>
          <div class="d-flex align-center ga-2">
            <code class="token-value">{{ field.value }}</code>
            <v-btn
              size="small"
              variant="tonal"
              :prepend-icon="copied === field.key ? 'mdi-check' : 'mdi-content-copy'"
              @click="copy(field.value, field.key)"
            >
              {{ copied === field.key ? $t('attendanceDevices.copied') : $t('attendanceDevices.copy') }}
            </v-btn>
          </div>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="flat" @click="issued = null">{{ $t('attendanceDevices.tokenSaved') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.install-steps {
  padding-left: 1.25rem;
  line-height: 1.9;
}
.token-value {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
  padding: 6px 8px;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.06);
}
</style>
