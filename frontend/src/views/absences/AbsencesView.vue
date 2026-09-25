<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import AbsenceFormDialog from '@/components/absences/AbsenceFormDialog.vue'
import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { absenceService } from '@/services/absenceService'
import { useAuthStore } from '@/stores/auth'
import type { AbsenceState, AbsenceType, EmployeeAbsence, LeaveBalance } from '@/types/models'
import type { ListParams } from '@/types/api'
import {
  ABSENCE_TYPES,
  absenceCategory,
  CATEGORY_CODE,
  STATE_COLOR,
  toIsoDate,
  type AbsenceCategory,
} from '@/utils/absences'
import { formatDate, todayIso as localTodayIso } from '@/utils/date'

const { t, tm } = useI18n()
const auth = useAuthStore()

const canView = computed(() => auth.can('absences.view'))
const canManage = computed(() => auth.can('absences.manage'))

// ---- List -------------------------------------------------------------

const filters = ref({
  type: null as AbsenceType | null,
  state: null as AbsenceState | null,
  from: '',
  to: '',
})

function filterParams(): ListParams {
  return {
    'filter[type]': filters.value.type ?? undefined,
    'filter[state]': filters.value.state ?? undefined,
    from: filters.value.from || undefined,
    to: filters.value.to || undefined,
  }
}

const { items, total, loading, page, itemsPerPage, search, reload } = usePaginatedResource<EmployeeAbsence>((params) =>
  absenceService.list({ ...params, ...filterParams() }),
)

watch(
  filters,
  () => {
    page.value = 1
    reload()
  },
  { deep: true },
)

const typeItems = computed(() => ABSENCE_TYPES.map((type) => ({ title: t(`absences.types.${type}`), value: type })))
const stateItems = computed(() =>
  (['upcoming', 'current', 'completed', 'cancelled'] as AbsenceState[]).map((state) => ({
    title: t(`absences.states.${state}`),
    value: state,
  })),
)

const headers = computed(() => [
  ...(canView.value ? [{ title: t('absences.employee'), key: 'employee', sortable: false }] : []),
  { title: t('absences.type'), key: 'type', sortable: false },
  { title: t('absences.period'), key: 'period', sortable: false },
  { title: t('absences.documentNumber'), key: 'document_number', sortable: false },
  { title: t('absences.state'), key: 'state', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const exporting = ref(false)
async function exportAs(format: 'csv' | 'xlsx' | 'pdf') {
  exporting.value = true
  try {
    await absenceService.export(format, { ...filterParams(), 'filter[search]': search.value || undefined })
  } finally {
    exporting.value = false
  }
}

// ---- Away today ---------------------------------------------------------

const awayToday = ref<EmployeeAbsence[]>([])
const awayGroups = computed(() => {
  const groups: Record<AbsenceCategory, EmployeeAbsence[]> = { vacation: [], business_trip: [], sick_leave: [], excused: [] }
  for (const absence of awayToday.value) groups[absenceCategory(absence.type)].push(absence)
  return (Object.keys(groups) as AbsenceCategory[]).map((category) => ({ category, absences: groups[category] }))
})

async function loadAwayToday() {
  if (!canView.value) return
  const result = await absenceService.list({ 'filter[state]': 'current', per_page: 500 })
  awayToday.value = result.data
}

// ---- Own balance (employees without HR access) ---------------------------

const balance = ref<LeaveBalance | null>(null)
async function loadBalance() {
  const employeeId = auth.user?.employee?.id
  if (canView.value || !employeeId) return
  balance.value = await absenceService.balance(employeeId)
}

// ---- Month schedule -------------------------------------------------------

const tab = ref<'list' | 'schedule'>('list')
const monthAnchor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const scheduleItems = ref<EmployeeAbsence[]>([])
const scheduleLoading = ref(false)
const todayIso = localTodayIso()

const monthLabel = computed(() => {
  const months = tm('calendar.months') as unknown as string[]
  return `${months[monthAnchor.value.getMonth()]} ${monthAnchor.value.getFullYear()}`
})

const monthDays = computed(() => {
  const year = monthAnchor.value.getFullYear()
  const month = monthAnchor.value.getMonth()
  const count = new Date(year, month + 1, 0).getDate()
  return Array.from({ length: count }, (_, i) => {
    const date = new Date(year, month, i + 1)
    return { day: i + 1, iso: toIsoDate(date), weekend: date.getDay() === 0 || date.getDay() === 6 }
  })
})

interface ScheduleRow {
  employeeId: number
  name: string
  department: string | null
  absences: EmployeeAbsence[]
  daysInMonth: number
}

const scheduleRows = computed<ScheduleRow[]>(() => {
  const first = monthDays.value[0]?.iso ?? ''
  const last = monthDays.value[monthDays.value.length - 1]?.iso ?? ''
  const rows = new Map<number, ScheduleRow>()

  for (const absence of scheduleItems.value) {
    const row = rows.get(absence.employee_id) ?? {
      employeeId: absence.employee_id,
      name: absence.employee?.full_name ?? '—',
      department: absence.employee?.department?.name ?? null,
      absences: [],
      daysInMonth: 0,
    }
    row.absences.push(absence)
    const from = absence.start_date > first ? absence.start_date : first
    const to = absence.end_date < last ? absence.end_date : last
    row.daysInMonth += monthDays.value.filter((d) => d.iso >= from && d.iso <= to).length
    rows.set(absence.employee_id, row)
  }

  return [...rows.values()].sort((a, b) => a.name.localeCompare(b.name))
})

function cellFor(row: ScheduleRow, iso: string) {
  const absence = row.absences.find((a) => a.start_date <= iso && a.end_date >= iso)
  if (!absence) return null
  const category = absenceCategory(absence.type)
  return {
    absence,
    category,
    code: CATEGORY_CODE[category],
    isStart: absence.start_date === iso,
    isEnd: absence.end_date === iso,
  }
}

async function loadSchedule() {
  scheduleLoading.value = true
  try {
    const days = monthDays.value
    const result = await absenceService.list({
      from: days[0].iso,
      to: days[days.length - 1].iso,
      'filter[state]': 'active',
      'filter[type]': filters.value.type ?? undefined,
      sort: 'start_date',
      per_page: 500,
    })
    scheduleItems.value = result.data
  } finally {
    scheduleLoading.value = false
  }
}

function shiftMonth(delta: number) {
  monthAnchor.value = new Date(monthAnchor.value.getFullYear(), monthAnchor.value.getMonth() + delta, 1)
}

watch([tab, monthAnchor, () => filters.value.type], () => {
  if (tab.value === 'schedule') loadSchedule()
})

// ---- Create / edit / cancel ----------------------------------------------

const formOpen = ref(false)
const editing = ref<EmployeeAbsence | null>(null)

function openCreate() {
  editing.value = null
  formOpen.value = true
}

function openEdit(absence: EmployeeAbsence) {
  editing.value = absence
  formOpen.value = true
}

function canEdit(absence: EmployeeAbsence): boolean {
  return canManage.value && absence.state !== 'cancelled'
}

async function refreshAll() {
  await Promise.all([reload(), loadAwayToday(), loadBalance(), tab.value === 'schedule' ? loadSchedule() : null])
}

const cancelTarget = ref<EmployeeAbsence | null>(null)
const cancelReason = ref('')
const cancelling = ref(false)

async function submitCancel() {
  if (!cancelTarget.value) return
  cancelling.value = true
  try {
    await absenceService.cancel(cancelTarget.value.id, cancelReason.value)
    cancelTarget.value = null
    cancelReason.value = ''
    await refreshAll()
  } finally {
    cancelling.value = false
  }
}

onMounted(() => {
  loadAwayToday()
  loadBalance()
})
</script>

<template>
  <AppPageHeader :title="canView ? $t('absences.title') : $t('absences.myTitle')">
    <template #actions>
      <v-menu v-if="canView">
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download" :loading="exporting">{{ $t('export.export') }}</v-btn>
        </template>
        <v-list density="compact">
          <v-list-item title="CSV" @click="exportAs('csv')" />
          <v-list-item title="Excel" @click="exportAs('xlsx')" />
          <v-list-item title="PDF" @click="exportAs('pdf')" />
        </v-list>
      </v-menu>
      <v-btn v-if="canManage" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ $t('absences.create') }}</v-btn>
    </template>
  </AppPageHeader>

  <v-card v-if="balance" class="mb-4">
    <v-card-title class="text-subtitle-1">{{ $t('absences.balanceTitle', { year: balance.year }) }}</v-card-title>
    <v-card-text class="d-flex flex-wrap ga-8">
      <div>
        <div class="text-headline-medium font-weight-bold text-primary">{{ balance.remaining_days }}</div>
        <div class="text-body-medium text-medium-emphasis">{{ $t('absences.balanceRemaining') }}</div>
      </div>
      <div>
        <div class="text-headline-medium">{{ balance.used_days }}</div>
        <div class="text-body-medium text-medium-emphasis">{{ $t('absences.balanceUsed') }}</div>
      </div>
      <div>
        <div class="text-headline-medium">{{ balance.entitlement_days }}</div>
        <div class="text-body-medium text-medium-emphasis">{{ $t('absences.balanceEntitlement') }}</div>
      </div>
    </v-card-text>
  </v-card>

  <v-card v-if="canView" class="mb-4">
    <v-card-title class="text-subtitle-1">{{ $t('absences.awayToday') }}</v-card-title>
    <v-card-text v-if="awayToday.length" class="away-grid">
      <div v-for="group in awayGroups" :key="group.category" class="away-group">
        <div class="d-flex align-center ga-2 mb-2">
          <span class="code-badge" :class="`code-${group.category}`">{{ CATEGORY_CODE[group.category] }}</span>
          <span class="text-body-medium font-weight-medium">{{ $t(`absences.categories.${group.category}`) }}</span>
          <span class="text-title-large ml-auto">{{ group.absences.length }}</span>
        </div>
        <div class="text-body-medium">
          <div v-for="absence in group.absences.slice(0, 6)" :key="absence.id" class="text-truncate">
            {{ absence.employee?.full_name }}
            <span class="text-medium-emphasis">— {{ formatDate(absence.end_date).slice(0, 5) }}</span>
          </div>
          <div v-if="group.absences.length > 6" class="text-medium-emphasis">+{{ group.absences.length - 6 }}</div>
        </div>
      </div>
    </v-card-text>
    <v-card-text v-else class="text-medium-emphasis">{{ $t('absences.nobodyAway') }}</v-card-text>
  </v-card>

  <div class="d-flex flex-wrap align-center ga-3 mb-3">
    <v-btn-toggle v-model="tab" mandatory density="comfortable" variant="outlined" color="primary">
      <v-btn value="list" prepend-icon="mdi-format-list-bulleted">{{ $t('absences.list') }}</v-btn>
      <v-btn value="schedule" prepend-icon="mdi-calendar-range">{{ $t('absences.schedule') }}</v-btn>
    </v-btn-toggle>
    <v-select
      v-model="filters.type"
      :items="typeItems"
      :label="$t('absences.allTypes')"
      density="compact"
      hide-details
      clearable
      style="max-width: 260px"
    />
    <template v-if="tab === 'list'">
      <v-select
        v-model="filters.state"
        :items="stateItems"
        :label="$t('absences.allStates')"
        density="compact"
        hide-details
        clearable
        style="max-width: 200px"
      />
      <v-text-field v-model="filters.from" type="date" :label="$t('attendance.from')" density="compact" hide-details style="max-width: 170px" />
      <v-text-field v-model="filters.to" type="date" :label="$t('attendance.to')" density="compact" hide-details style="max-width: 170px" />
    </template>
  </div>

  <AppDataTable
    v-if="tab === 'list'"
    :headers="headers"
    :items="items"
    :items-length="total"
    :loading="loading"
    :page="page"
    :items-per-page="itemsPerPage"
    @update:page="(v: number) => (page = v)"
    @update:items-per-page="(v: number) => (itemsPerPage = v)"
    @update:search="(v: string) => (search = v)"
  >
    <template #item.employee="{ item }">
      <router-link
        v-if="auth.can('employees.view')"
        :to="{ name: 'employee-detail', params: { id: item.employee_id } }"
        class="text-decoration-none"
      >
        {{ item.employee?.full_name }}
      </router-link>
      <span v-else>{{ item.employee?.full_name }}</span>
      <div class="text-body-small text-medium-emphasis">{{ item.employee?.department?.name }}</div>
    </template>
    <template #item.type="{ item }">
      <div class="d-flex align-center ga-2">
        <span class="code-badge" :class="`code-${absenceCategory(item.type)}`">{{ CATEGORY_CODE[absenceCategory(item.type)] }}</span>
        <div>
          <div>{{ $t(`absences.types.${item.type}`) }}</div>
          <div v-if="item.destination" class="text-body-small text-medium-emphasis">{{ item.destination }}</div>
        </div>
      </div>
    </template>
    <template #item.period="{ item }">
      <div>{{ formatDate(item.start_date) }} — {{ formatDate(item.end_date) }}</div>
      <div class="text-body-small text-medium-emphasis">{{ $t('absences.days', { count: item.days }) }}</div>
    </template>
    <template #item.document_number="{ item }">
      <div>{{ item.document_number ?? '—' }}</div>
      <div v-if="item.document_date" class="text-body-small text-medium-emphasis">{{ formatDate(item.document_date) }}</div>
    </template>
    <template #item.state="{ item }">
      <v-chip :color="STATE_COLOR[item.state]" size="small" variant="tonal" label>{{ $t(`absences.states.${item.state}`) }}</v-chip>
      <div v-if="item.cancellation_reason" class="text-body-small text-medium-emphasis mt-1">
        {{ $t('absences.cancelledReason', { reason: item.cancellation_reason }) }}
      </div>
    </template>
    <template #item.actions="{ item }">
      <v-btn v-if="item.download_url" icon="mdi-paperclip" variant="text" size="small" @click="absenceService.download(item)" />
      <v-btn v-if="canEdit(item)" icon="mdi-pencil-outline" variant="text" size="small" @click="openEdit(item)" />
      <v-btn
        v-if="canEdit(item)"
        icon="mdi-cancel"
        variant="text"
        size="small"
        color="error"
        :title="$t('absences.cancel')"
        @click="cancelTarget = item"
      />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-calendar-remove-outline" :message="$t('absences.noAbsences')" />
    </template>
  </AppDataTable>

  <v-card v-else>
    <v-card-text class="d-flex align-center ga-2 pb-2">
      <v-btn icon="mdi-chevron-left" variant="text" size="small" @click="shiftMonth(-1)" />
      <span class="text-subtitle-1 font-weight-medium month-label">{{ monthLabel }}</span>
      <v-btn icon="mdi-chevron-right" variant="text" size="small" @click="shiftMonth(1)" />
      <v-spacer />
      <span class="text-body-small text-medium-emphasis">{{ $t('absences.legend') }}</span>
    </v-card-text>
    <v-progress-linear v-if="scheduleLoading" indeterminate color="primary" />
    <div v-if="scheduleRows.length" class="schedule-scroll">
      <table class="schedule">
        <thead>
          <tr>
            <th class="name-col">{{ $t('absences.employee') }}</th>
            <th v-for="d in monthDays" :key="d.iso" :class="{ weekend: d.weekend, today: d.iso === todayIso }">{{ d.day }}</th>
            <th class="total-col">Σ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in scheduleRows" :key="row.employeeId">
            <td class="name-col">
              <div class="text-truncate">{{ row.name }}</div>
              <div v-if="row.department" class="text-body-small text-medium-emphasis text-truncate">{{ row.department }}</div>
            </td>
            <td v-for="d in monthDays" :key="d.iso" :class="{ weekend: d.weekend, today: d.iso === todayIso }">
              <template v-for="cell in [cellFor(row, d.iso)]" :key="d.iso">
                <button
                  v-if="cell"
                  type="button"
                  class="bar"
                  :class="[`code-${cell.category}`, { start: cell.isStart, end: cell.isEnd }]"
                  :title="`${$t(`absences.types.${cell.absence.type}`)}: ${formatDate(cell.absence.start_date)} — ${formatDate(cell.absence.end_date)}`"
                  :disabled="!canEdit(cell.absence)"
                  @click="openEdit(cell.absence)"
                >
                  {{ cell.code }}
                </button>
              </template>
            </td>
            <td class="total-col">{{ row.daysInMonth }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <v-card-text v-else-if="!scheduleLoading" class="text-medium-emphasis">{{ $t('absences.scheduleEmpty') }}</v-card-text>
  </v-card>

  <AbsenceFormDialog v-model="formOpen" :absence="editing" @saved="refreshAll" />

  <v-dialog :model-value="cancelTarget !== null" max-width="460" @update:model-value="(v: boolean) => !v && (cancelTarget = null)">
    <v-card>
      <v-card-title>{{ $t('absences.cancelTitle') }}</v-card-title>
      <v-card-text>
        <p class="text-body-medium mb-4">{{ $t('absences.cancelHint') }}</p>
        <v-text-field v-model="cancelReason" :label="$t('absences.cancelReason')" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="cancelTarget = null">{{ $t('common.close') }}</v-btn>
        <v-btn color="error" variant="flat" :loading="cancelling" @click="submitCancel">{{ $t('absences.cancel') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.away-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.away-group {
  min-width: 0;
}

.code-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.code-vacation {
  background: rgb(var(--v-theme-info));
}

.code-business_trip {
  background: rgb(var(--v-theme-primary));
}

.code-sick_leave {
  background: rgb(var(--v-theme-warning));
}

.code-excused {
  background: rgb(var(--v-theme-absence-other));
}

.month-label {
  min-width: 140px;
  text-align: center;
}

.schedule-scroll {
  overflow-x: auto;
}

.schedule {
  border-collapse: collapse;
  width: 100%;
  font-size: 12px;
}

.schedule th,
.schedule td {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 0;
  height: 40px;
  min-width: 28px;
  text-align: center;
}

.schedule th {
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.schedule .weekend {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.schedule th.today {
  color: rgb(var(--v-theme-primary));
  font-weight: 700;
}

.schedule td.today {
  box-shadow: inset 1px 0 0 rgb(var(--v-theme-primary)), inset -1px 0 0 rgb(var(--v-theme-primary));
}

.schedule .name-col {
  position: sticky;
  left: 0;
  z-index: 1;
  background: rgb(var(--v-theme-surface));
  text-align: left;
  padding: 4px 12px;
  min-width: 200px;
  max-width: 240px;
}

.schedule .total-col {
  padding: 0 8px;
  font-weight: 600;
}

.bar {
  display: block;
  width: 100%;
  height: 24px;
  border: 0;
  color: #fff;
  font-weight: 700;
  font-size: 11px;
  line-height: 24px;
  cursor: pointer;
}

.bar:disabled {
  cursor: default;
}

.bar:focus-visible {
  outline: 2px solid rgb(var(--v-theme-on-surface));
  outline-offset: -2px;
}

.bar.start {
  border-top-left-radius: 6px;
  border-bottom-left-radius: 6px;
  margin-left: 2px;
  width: calc(100% - 2px);
}

.bar.end {
  border-top-right-radius: 6px;
  border-bottom-right-radius: 6px;
  width: calc(100% - 2px);
}

.bar.start.end {
  width: calc(100% - 4px);
}
</style>
