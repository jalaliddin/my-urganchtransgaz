<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { attendanceDeviceService } from '@/services/attendanceDeviceService'
import { departmentService } from '@/services/departmentService'
import { employeeService } from '@/services/employeeService'
import { organizationService } from '@/services/organizationService'
import { positionService } from '@/services/positionService'
import { useAuthStore } from '@/stores/auth'
import type { Department, DevicePeopleComparison, DevicePerson, Employee, Organization, Position } from '@/types/models'
import { formatDateTime } from '@/utils/date'

const { t } = useI18n()
const auth = useAuthStore()

const data = ref<DevicePeopleComparison | null>(null)
const loading = ref(true)
const notice = ref<{ text: string; color: string } | null>(null)

async function load() {
  loading.value = true
  try {
    data.value = await attendanceDeviceService.people()
  } finally {
    loading.value = false
  }
}

onMounted(load)

const hasRoster = computed(() => data.value?.devices.some((device) => device.roster_synced_at) ?? false)

// --- Filtering ---------------------------------------------------------------
const show = ref<'unlinked' | 'linked' | 'all'>('unlinked')
const search = ref('')
const rows = computed(() => {
  const term = search.value.trim().toLowerCase()

  return (data.value?.people ?? []).filter(
    (person) =>
      (show.value === 'all' || (show.value === 'linked') === (person.employee !== null)) &&
      (!term ||
        person.user_id.toLowerCase().includes(term) ||
        (person.name ?? '').toLowerCase().includes(term) ||
        (person.employee?.full_name ?? '').toLowerCase().includes(term)),
  )
})

const headers = computed(() => [
  { title: t('attendanceDevices.dahuaId'), key: 'user_id', width: 110 },
  { title: t('attendanceDevices.terminalName'), key: 'name' },
  { title: t('attendanceDevices.terminals'), key: 'devices', sortable: false },
  { title: t('attendanceDevices.employee'), key: 'employee', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
])

// --- Linking -----------------------------------------------------------------
const busy = ref<string | null>(null)

async function link(person: DevicePerson, employeeId: number) {
  busy.value = person.user_id
  try {
    await attendanceDeviceService.link(person.user_id, employeeId)
    notice.value = { text: t('attendanceDevices.linked'), color: 'success' }
    await load()
    return true
  } catch (error: unknown) {
    const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    notice.value = { text: Object.values(response?.data?.errors ?? {})[0]?.[0] ?? response?.data?.message ?? t('common.genericError'), color: 'error' }
    return false
  } finally {
    busy.value = null
  }
}

async function unlink(employeeId: number) {
  await attendanceDeviceService.unlink(employeeId)
  await load()
}

// "Boshqa xodim…": pick any employee by search.
const linkTarget = ref<DevicePerson | null>(null)
const employeeQuery = ref('')
const employeeOptions = ref<Employee[]>([])
const chosenEmployee = ref<number | null>(null)
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(employeeQuery, (query) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    employeeOptions.value = (await employeeService.list({ per_page: 20, 'filter[search]': query || undefined })).data
  }, 300)
})

function openLink(person: DevicePerson) {
  linkTarget.value = person
  chosenEmployee.value = null
  employeeQuery.value = person.name?.split(/\s+/)[0] ?? ''
}

async function confirmLink() {
  if (!linkTarget.value || !chosenEmployee.value) return
  if (await link(linkTarget.value, chosenEmployee.value)) linkTarget.value = null
}

// "Yangi xodim": create the employee, already carrying the Dahua ID.
const createTarget = ref<DevicePerson | null>(null)
const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
const positions = ref<Position[]>([])
const form = ref({ last_name: '', first_name: '', middle_name: '', employee_number: '', organization_id: null as number | null, department_id: null as number | null, position_id: null as number | null })
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

async function openCreate(person: DevicePerson) {
  // "Surname Nodir" gives both names; "Surname N.O." only the surname.
  const [lastName = '', given = ''] = (person.name ?? '').trim().split(/\s+/)
  const isInitials = given.includes('.') || (given.length <= 3 && given === given.toUpperCase())
  form.value = {
    last_name: lastName,
    first_name: isInitials ? '' : given.charAt(0).toUpperCase() + given.slice(1),
    middle_name: '',
    employee_number: '',
    organization_id: null,
    department_id: null,
    position_id: null,
  }
  formErrors.value = {}
  createTarget.value = person
  if (organizations.value.length === 0) organizations.value = (await organizationService.list({ per_page: 200 })).data
}

watch(
  () => form.value.organization_id,
  async (organizationId) => {
    form.value.department_id = null
    form.value.position_id = null
    if (!organizationId) return
    ;[departments.value, positions.value] = await Promise.all([
      departmentService.list({ per_page: 200, 'filter[organization_id]': organizationId }).then((r) => r.data),
      positionService.list({ per_page: 200, 'filter[organization_id]': organizationId }).then((r) => r.data),
    ])
  },
)

async function createEmployee() {
  if (!createTarget.value) return
  saving.value = true
  formErrors.value = {}
  try {
    await employeeService.create({ ...form.value, middle_name: form.value.middle_name || null, dahua_person_id: createTarget.value.user_id })
    createTarget.value = null
    notice.value = { text: t('attendanceDevices.created'), color: 'success' }
    await load()
  } catch (error: unknown) {
    formErrors.value = (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="loading && !data" class="d-flex justify-center py-12"><v-progress-circular indeterminate /></div>

  <template v-else-if="data">
    <v-alert v-if="!hasRoster" type="info" variant="tonal" class="mb-4">{{ $t('attendanceDevices.noRosterYet') }}</v-alert>

    <v-row dense class="mb-2">
      <v-col v-for="tile in [
        { key: 'on_terminals', icon: 'mdi-gate', color: 'primary' },
        { key: 'linked', icon: 'mdi-link-variant', color: 'success' },
        { key: 'unlinked', icon: 'mdi-account-question-outline', color: 'warning' },
        { key: 'missing_on_terminals', icon: 'mdi-account-off-outline', color: 'secondary' },
      ] as const" :key="tile.key" cols="6" md="3">
        <v-card variant="outlined" class="pa-3 h-100">
          <v-icon :icon="tile.icon" :color="tile.color" size="20" />
          <div class="text-h5 font-weight-bold mt-1">{{ data.summary[tile.key] }}</div>
          <div class="text-body-2 text-medium-emphasis">{{ $t(`attendanceDevices.summary.${tile.key}`) }}</div>
        </v-card>
      </v-col>
    </v-row>

    <div class="text-body-2 text-medium-emphasis mb-4">
      <span v-for="device in data.devices" :key="device.id" class="mr-4">
        <code>{{ device.device_id }}</code>:
        <template v-if="device.roster_synced_at">
          {{ $t('attendanceDevices.rosterInfo', { count: device.people_count, time: formatDateTime(device.roster_synced_at) }) }}
          <span v-if="!device.roster_complete" class="text-warning">({{ $t('attendanceDevices.rosterPartial') }})</span>
        </template>
        <template v-else>{{ $t('attendanceDevices.rosterNever') }}</template>
      </span>
    </div>

    <v-card>
      <v-card-text class="d-flex flex-wrap align-center ga-3">
        <v-chip-group v-model="show" mandatory selected-class="text-primary" column>
          <v-chip value="unlinked" filter variant="outlined">{{ $t('attendanceDevices.summary.unlinked') }}</v-chip>
          <v-chip value="linked" filter variant="outlined">{{ $t('attendanceDevices.summary.linked') }}</v-chip>
          <v-chip value="all" filter variant="outlined">{{ $t('attendanceDevices.showAll') }}</v-chip>
        </v-chip-group>
        <v-text-field
          v-model="search"
          :placeholder="$t('attendanceDevices.searchPeople')"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          hide-details
          clearable
          style="min-width: 220px; max-width: 320px"
        />
        <v-spacer />
        <v-btn variant="text" prepend-icon="mdi-refresh" :loading="loading" @click="load">{{ $t('attendanceDevices.refresh') }}</v-btn>
      </v-card-text>

      <v-data-table :headers="headers" :items="rows" item-value="user_id" :items-per-page="25" density="comfortable">
        <template #item.user_id="{ item }"><code>{{ item.user_id }}</code></template>
        <template #item.name="{ item }">{{ item.name || '—' }}</template>
        <template #item.devices="{ item }">
          <v-chip v-for="device in item.devices" :key="device" size="x-small" class="mr-1" variant="tonal">{{ device }}</v-chip>
        </template>
        <template #item.employee="{ item }">
          <div v-if="item.employee" class="d-flex align-center ga-2 py-1">
            <v-icon icon="mdi-link-variant" color="success" size="18" />
            <div>
              <RouterLink :to="`/employees/${item.employee.id}`">{{ item.employee.full_name }}</RouterLink>
              <div class="text-caption text-medium-emphasis">{{ item.employee.employee_number }} · {{ item.employee.organization ?? '—' }}</div>
            </div>
          </div>
          <div v-else class="d-flex flex-wrap ga-1 py-1">
            <v-chip
              v-for="suggestion in item.suggestions"
              :key="suggestion.id"
              size="small"
              :color="suggestion.strong ? 'primary' : undefined"
              :variant="suggestion.strong ? 'flat' : 'outlined'"
              prepend-icon="mdi-link-variant-plus"
              :disabled="busy === item.user_id"
              :title="`${suggestion.employee_number} · ${suggestion.organization ?? ''}`"
              @click="link(item, suggestion.id)"
            >
              {{ suggestion.full_name }}
            </v-chip>
            <span v-if="!item.suggestions.length" class="text-caption text-medium-emphasis">{{ $t('attendanceDevices.noSuggestion') }}</span>
          </div>
        </template>
        <template #item.actions="{ item }">
          <v-btn v-if="item.employee" size="small" variant="text" color="error" @click="unlink(item.employee.id)">
            {{ $t('attendanceDevices.unlink') }}
          </v-btn>
          <template v-else>
            <v-btn size="small" variant="text" @click="openLink(item)">{{ $t('attendanceDevices.linkOther') }}</v-btn>
            <v-btn v-if="auth.can('employees.create')" size="small" variant="tonal" color="primary" @click="openCreate(item)">
              {{ $t('attendanceDevices.newEmployee') }}
            </v-btn>
          </template>
        </template>
        <template #no-data>
          <AppEmptyState icon="mdi-account-check-outline" :message="hasRoster ? $t('attendanceDevices.nobodyHere') : $t('attendanceDevices.rosterNever')" />
        </template>
      </v-data-table>
    </v-card>

    <v-expansion-panels v-if="data.missing_on_terminals.length" class="mt-4">
      <v-expansion-panel>
        <v-expansion-panel-title>
          {{ $t('attendanceDevices.summary.missing_on_terminals') }} ({{ data.missing_on_terminals.length }})
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <p class="text-body-2 text-medium-emphasis mb-2">{{ $t('attendanceDevices.missingHint') }}</p>
          <v-list density="compact">
            <v-list-item v-for="employee in data.missing_on_terminals" :key="employee.id">
              <v-list-item-title>
                <RouterLink :to="`/employees/${employee.id}`">{{ employee.full_name }}</RouterLink>
              </v-list-item-title>
              <v-list-item-subtitle>
                {{ employee.employee_number }} · {{ employee.organization ?? '—' }} · Dahua ID <code>{{ employee.dahua_person_id }}</code>
              </v-list-item-subtitle>
              <template #append>
                <v-btn size="small" variant="text" color="error" @click="unlink(employee.id)">{{ $t('attendanceDevices.unlink') }}</v-btn>
              </template>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </template>

  <v-dialog :model-value="linkTarget !== null" max-width="520" @update:model-value="linkTarget = null">
    <v-card v-if="linkTarget">
      <v-card-title>{{ $t('attendanceDevices.linkTitle', { id: linkTarget.user_id, name: linkTarget.name ?? '' }) }}</v-card-title>
      <v-card-text>
        <v-autocomplete
          v-model="chosenEmployee"
          v-model:search="employeeQuery"
          :items="employeeOptions"
          :item-title="(employee: Employee) => `${employee.full_name} (${employee.employee_number})`"
          item-value="id"
          no-filter
          autofocus
          :label="$t('attendanceDevices.employee')"
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props" :subtitle="item.dahua_person_id ? `Dahua ID: ${item.dahua_person_id}` : item.organization?.name" />
          </template>
        </v-autocomplete>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="linkTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :disabled="!chosenEmployee" :loading="busy !== null" @click="confirmLink">
          {{ $t('attendanceDevices.link') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog :model-value="createTarget !== null" max-width="560" @update:model-value="createTarget = null">
    <v-card v-if="createTarget">
      <v-card-title>{{ $t('attendanceDevices.newEmployeeTitle', { id: createTarget.user_id }) }}</v-card-title>
      <v-card-subtitle>{{ $t('attendanceDevices.terminalName') }}: {{ createTarget.name ?? '—' }}</v-card-subtitle>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6"><v-text-field v-model="form.last_name" :label="$t('employees.lastName')" :error-messages="formErrors.last_name" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model="form.first_name" :label="$t('employees.firstName')" :error-messages="formErrors.first_name" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model="form.middle_name" :label="$t('employees.middleName')" :error-messages="formErrors.middle_name" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model="form.employee_number" :label="$t('employees.employeeNumber')" :error-messages="formErrors.employee_number" /></v-col>
          <v-col cols="12">
            <v-autocomplete v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" :label="$t('employees.organization')" :error-messages="formErrors.organization_id" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-autocomplete v-model="form.department_id" :items="departments" item-title="name" item-value="id" clearable :disabled="!form.organization_id" :label="$t('employees.department')" :error-messages="formErrors.department_id" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-autocomplete v-model="form.position_id" :items="positions" item-title="name" item-value="id" clearable :disabled="!form.organization_id" :label="$t('employees.position')" :error-messages="formErrors.position_id" />
          </v-col>
        </v-row>
        <div v-if="formErrors.dahua_person_id" class="text-error text-caption">{{ formErrors.dahua_person_id[0] }}</div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="createTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="createEmployee">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar :model-value="notice !== null" :color="notice?.color" :timeout="7000" @update:model-value="notice = null">
    {{ notice?.text }}
  </v-snackbar>
</template>
