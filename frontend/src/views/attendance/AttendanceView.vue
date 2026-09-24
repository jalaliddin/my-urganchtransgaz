<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

import { attendanceService } from '@/services/attendanceService'
import { departmentService } from '@/services/departmentService'
import { organizationService } from '@/services/organizationService'
import { useAuthStore } from '@/stores/auth'
import type {
  AttendanceEvent,
  AttendanceRecord,
  AttendanceReportRow,
  AttendanceStatus,
  Department,
  Organization,
  Timesheet,
  TodayAttendance,
} from '@/types/models'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const canManage = computed(() => auth.can('attendance.manage'))

// Lets the Reports hub deep-link straight into a tab, e.g. /attendance?tab=report.
const validTabs = ['today', 'report', 'timesheet']
const initialTab = typeof route.query.tab === 'string' && validTabs.includes(route.query.tab) ? route.query.tab : 'today'
const tab = ref(initialTab)

/**
 * Local calendar date, not toISOString().slice(0, 10) — that reads the UTC
 * date, which can land on the wrong day once the browser's local time and
 * APP_TIMEZONE (Asia/Tashkent) disagree about what "today" is.
 */
function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function timeOnly(value: string | null): string {
  return value ? value.slice(11, 16) : '—'
}

// --- Today board ---
const today = ref<TodayAttendance[]>([])
const loadingToday = ref(false)
const checkingInOut = ref(false)

const myRow = computed(() => today.value.find((row) => row.employee_id === auth.user?.employee?.id) ?? null)

async function loadToday() {
  loadingToday.value = true
  try {
    today.value = await attendanceService.today()
  } finally {
    loadingToday.value = false
  }
}

async function checkIn() {
  checkingInOut.value = true
  try {
    await attendanceService.checkIn()
    await loadToday()
  } finally {
    checkingInOut.value = false
  }
}

async function checkOut() {
  checkingInOut.value = true
  try {
    await attendanceService.checkOut()
    await loadToday()
  } finally {
    checkingInOut.value = false
  }
}

// --- Manual correction (HR/admin), scoped to today's record ---
const statusOptions = computed(() =>
  (['present', 'late', 'early_leave', 'absent', 'business_trip', 'vacation', 'sick_leave'] as AttendanceStatus[]).map(
    (value) => ({ title: t(`status.${value}`), value }),
  ),
)

const editTarget = ref<TodayAttendance | null>(null)
const editForm = ref({ check_in: '', check_out: '', status: '' as AttendanceStatus | '', notes: '' })
const editErrors = ref<Record<string, string[]>>({})
const savingEdit = ref(false)

function openEdit(row: TodayAttendance) {
  editTarget.value = row
  editForm.value = {
    check_in: row.attendance?.check_in ? timeOnly(row.attendance.check_in) : '',
    check_out: row.attendance?.check_out ? timeOnly(row.attendance.check_out) : '',
    status: row.attendance?.status ?? '',
    notes: row.attendance?.notes ?? '',
  }
  editErrors.value = {}
}

async function saveEdit() {
  if (!editTarget.value) return
  savingEdit.value = true
  editErrors.value = {}
  try {
    const payload = {
      check_in: editForm.value.check_in || null,
      check_out: editForm.value.check_out || null,
      status: editForm.value.status || undefined,
      notes: editForm.value.notes || null,
    }
    if (editTarget.value.attendance) {
      await attendanceService.update(editTarget.value.attendance.id, payload)
    } else {
      await attendanceService.create({
        employee_id: editTarget.value.employee_id,
        date: toLocalDateString(new Date()),
        ...payload,
      })
    }
    editTarget.value = null
    await loadToday()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    editErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    savingEdit.value = false
  }
}

// --- Report ---
const reportRows = ref<AttendanceReportRow[]>([])
const loadingReport = ref(false)
const reportFilters = ref({
  group_by: 'employee' as 'employee' | 'department' | 'organization',
  from: toLocalDateString(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
  to: toLocalDateString(new Date()),
})

const groupByOptions = computed(() => [
  { title: t('employees.title'), value: 'employee' },
  { title: t('departments.title'), value: 'department' },
  { title: t('organizations.title'), value: 'organization' },
])

async function loadReport() {
  loadingReport.value = true
  try {
    reportRows.value = await attendanceService.report(reportFilters.value)
  } finally {
    loadingReport.value = false
  }
}

const exportingReport = ref(false)
async function exportReport(format: 'csv' | 'xlsx' | 'pdf') {
  exportingReport.value = true
  try {
    await attendanceService.exportReport(format, reportFilters.value)
  } finally {
    exportingReport.value = false
  }
}

function formatMinutes(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return `${hours}${t('attendance.hoursShort')} ${mins}${t('attendance.minutesShort')}`
}

// --- Report row detail (batafsil): day-by-day records behind one report row ---
const detailTarget = ref<AttendanceReportRow | null>(null)
const detailRows = ref<AttendanceRecord[]>([])
const loadingDetail = ref(false)

async function openDetail(row: AttendanceReportRow) {
  if (reportFilters.value.group_by !== 'employee') return
  detailTarget.value = row
  loadingDetail.value = true
  try {
    const result = await attendanceService.list({
      'filter[employee_id]': row.group_id,
      from: reportFilters.value.from,
      to: reportFilters.value.to,
      per_page: 100,
      sort: '-date',
    })
    detailRows.value = result.data
  } finally {
    loadingDetail.value = false
  }
}

// --- Per-day raw scan log (necha bora kirib chiqqan) behind one detail row ---
const eventsTarget = ref<AttendanceRecord | null>(null)
const eventsList = ref<AttendanceEvent[]>([])
const loadingEvents = ref(false)

async function openEvents(record: AttendanceRecord) {
  eventsTarget.value = record
  loadingEvents.value = true
  try {
    eventsList.value = await attendanceService.events(record.id)
  } finally {
    loadingEvents.value = false
  }
}

// --- Tabel (monthly timesheet) ---
const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
const timesheet = ref<Timesheet | null>(null)
const loadingTimesheet = ref(false)
const exportingTimesheet = ref(false)

function currentMonthString(): string {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

const timesheetFilters = ref({
  month: currentMonthString(),
  organization_id: undefined as number | undefined,
  department_id: undefined as number | undefined,
  employee_id: undefined as number | undefined,
})

watch(
  () => timesheetFilters.value.organization_id,
  async (organizationId) => {
    timesheetFilters.value.department_id = undefined
    if (!organizationId) {
      departments.value = []
      return
    }
    const result = await departmentService.list({ per_page: 200, 'filter[organization_id]': organizationId })
    departments.value = result.data
  },
)

async function loadTimesheet() {
  loadingTimesheet.value = true
  try {
    timesheet.value = await attendanceService.timesheet(timesheetFilters.value)
  } finally {
    loadingTimesheet.value = false
  }
}

async function exportTimesheet(format: 'csv' | 'xlsx' | 'pdf') {
  exportingTimesheet.value = true
  try {
    await attendanceService.exportTimesheet(format, timesheetFilters.value)
  } finally {
    exportingTimesheet.value = false
  }
}

/** A day cell's worked hours, short — "8", "7.5" — blank when there is nothing to show. */
function cellHours(minutes: number | null): string {
  if (!minutes) return ''
  const hours = minutes / 60
  return Number.isInteger(hours) ? String(hours) : hours.toFixed(1)
}

const dayHeaders = computed(() => (timesheet.value ? Array.from({ length: timesheet.value.day_count }, (_, i) => i + 1) : []))

onMounted(() => {
  loadToday()
  loadReport()
  if (tab.value === 'timesheet') loadTimesheet()
  organizationService.list({ per_page: 100 }).then((result) => (organizations.value = result.data))
})

watch(tab, (value) => {
  if (value === 'timesheet' && !timesheet.value) {
    loadTimesheet()
  }
})
</script>

<template>
  <AppPageHeader :title="$t('nav.attendance')" />

  <v-tabs v-model="tab" class="mb-4">
    <v-tab value="today">{{ $t('attendance.tabToday') }}</v-tab>
    <v-tab value="report">{{ $t('attendance.tabReport') }}</v-tab>
    <v-tab value="timesheet">{{ $t('attendance.tabTimesheet') }}</v-tab>
  </v-tabs>

  <v-window v-model="tab">
    <v-window-item value="today">
      <v-card v-if="auth.user?.employee" class="mb-4">
        <v-card-text class="d-flex align-center flex-wrap ga-4">
          <div>
            <div class="text-subtitle-2 text-medium-emphasis">{{ $t('attendance.myStatusToday') }}</div>
            <AppStatusChip v-if="myRow?.attendance" :status="myRow.attendance.status" />
            <span v-else class="text-body-2">{{ $t('attendance.noRecordYet') }}</span>
          </div>
          <div v-if="myRow?.attendance?.check_in" class="text-body-2">
            {{ $t('attendance.checkIn') }}: {{ timeOnly(myRow.attendance.check_in) }}
          </div>
          <div v-if="myRow?.attendance?.check_out" class="text-body-2">
            {{ $t('attendance.checkOut') }}: {{ timeOnly(myRow.attendance.check_out) }}
          </div>
          <v-spacer />
          <v-btn
            color="primary"
            variant="flat"
            :loading="checkingInOut"
            :disabled="Boolean(myRow?.attendance?.check_in)"
            @click="checkIn"
          >
            {{ $t('attendance.checkIn') }}
          </v-btn>
          <v-btn
            color="secondary"
            variant="flat"
            :loading="checkingInOut"
            :disabled="!myRow?.attendance?.check_in || Boolean(myRow?.attendance?.check_out)"
            @click="checkOut"
          >
            {{ $t('attendance.checkOut') }}
          </v-btn>
        </v-card-text>
      </v-card>

      <v-card>
        <v-table>
          <thead>
            <tr>
              <th>{{ $t('employees.fullName') }}</th>
              <th>{{ $t('employees.department') }}</th>
              <th>{{ $t('attendance.checkIn') }}</th>
              <th>{{ $t('attendance.checkOut') }}</th>
              <th>{{ $t('common.status') }}</th>
              <th v-if="canManage" class="text-end">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in today" :key="row.employee_id">
              <td>{{ row.full_name }}</td>
              <td>{{ row.department?.name ?? '—' }}</td>
              <td>{{ timeOnly(row.attendance?.check_in ?? null) }}</td>
              <td>{{ timeOnly(row.attendance?.check_out ?? null) }}</td>
              <td>
                <AppStatusChip v-if="row.attendance" :status="row.attendance.status" />
                <span v-else class="text-medium-emphasis">{{ $t('attendance.noRecordYet') }}</span>
              </td>
              <td v-if="canManage" class="text-end">
                <v-btn icon="mdi-pencil-outline" variant="text" size="small" @click="openEdit(row)" />
              </td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingToday && today.length === 0" icon="mdi-calendar-check-outline" />
      </v-card>
    </v-window-item>

    <v-window-item value="report">
      <v-card class="mb-4">
        <v-card-text class="d-flex flex-wrap ga-4">
          <v-select
            v-model="reportFilters.group_by"
            :items="groupByOptions"
            :label="$t('attendance.groupBy')"
            style="max-width: 220px"
            hide-details
          />
          <v-text-field
            v-model="reportFilters.from"
            type="date"
            :label="$t('attendance.from')"
            style="max-width: 200px"
            hide-details
          />
          <v-text-field
            v-model="reportFilters.to"
            type="date"
            :label="$t('attendance.to')"
            style="max-width: 200px"
            hide-details
          />
          <v-btn color="primary" variant="flat" :loading="loadingReport" @click="loadReport">
            {{ $t('common.search') }}
          </v-btn>
          <v-spacer />
          <v-menu>
            <template #activator="{ props }">
              <v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download" :loading="exportingReport">
                {{ $t('export.export') }}
              </v-btn>
            </template>
            <v-list>
              <v-list-item title="CSV" @click="exportReport('csv')" />
              <v-list-item title="Excel" @click="exportReport('xlsx')" />
              <v-list-item title="PDF" @click="exportReport('pdf')" />
            </v-list>
          </v-menu>
        </v-card-text>
      </v-card>

      <v-card v-if="reportRows.length" class="mb-4">
        <v-card-text>
          <AppChart type="bar" :labels="reportRows.map((r) => r.label)" :data="reportRows.map((r) => r.total_days)" :label="$t('attendance.totalDays')" />
        </v-card-text>
      </v-card>

      <v-card>
        <v-table>
          <thead>
            <tr>
              <th>{{ $t('attendance.group') }}</th>
              <th>{{ $t('attendance.totalDays') }}</th>
              <th>{{ $t('attendance.totalWorked') }}</th>
              <th>{{ $t('status.present') }}</th>
              <th>{{ $t('status.late') }}</th>
              <th>{{ $t('status.early_leave') }}</th>
              <th>{{ $t('status.absent') }}</th>
              <th>{{ $t('status.business_trip') }}</th>
              <th>{{ $t('status.vacation') }}</th>
              <th>{{ $t('status.sick_leave') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in reportRows"
              :key="row.group_id"
              :style="reportFilters.group_by === 'employee' ? { cursor: 'pointer' } : undefined"
              @click="openDetail(row)"
            >
              <td>{{ row.label }}</td>
              <td>{{ row.total_days }}</td>
              <td>{{ formatMinutes(row.total_worked_minutes) }}</td>
              <td>{{ row.present_count }}</td>
              <td>{{ row.late_count }}</td>
              <td>{{ row.early_leave_count }}</td>
              <td>{{ row.absent_count }}</td>
              <td>{{ row.business_trip_count }}</td>
              <td>{{ row.vacation_count }}</td>
              <td>{{ row.sick_leave_count }}</td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingReport && reportRows.length === 0" icon="mdi-chart-bar" />
      </v-card>
    </v-window-item>

    <v-window-item value="timesheet">
      <v-card class="mb-4">
        <v-card-text class="d-flex flex-wrap ga-4">
          <v-text-field
            v-model="timesheetFilters.month"
            type="month"
            :label="$t('attendance.month')"
            style="max-width: 180px"
            hide-details
          />
          <v-select
            v-model="timesheetFilters.organization_id"
            :items="organizations.map((o) => ({ title: o.name, value: o.id }))"
            :label="$t('employees.organization')"
            style="max-width: 220px"
            clearable
            hide-details
          />
          <v-select
            v-model="timesheetFilters.department_id"
            :items="departments.map((d) => ({ title: d.name, value: d.id }))"
            :label="$t('employees.department')"
            style="max-width: 220px"
            clearable
            :disabled="!timesheetFilters.organization_id"
            hide-details
          />
          <v-btn color="primary" variant="flat" :loading="loadingTimesheet" @click="loadTimesheet">
            {{ $t('common.search') }}
          </v-btn>
          <v-spacer />
          <v-menu>
            <template #activator="{ props }">
              <v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download" :loading="exportingTimesheet">
                {{ $t('export.export') }}
              </v-btn>
            </template>
            <v-list>
              <v-list-item title="CSV" @click="exportTimesheet('csv')" />
              <v-list-item title="Excel" @click="exportTimesheet('xlsx')" />
              <v-list-item title="PDF" @click="exportTimesheet('pdf')" />
            </v-list>
          </v-menu>
        </v-card-text>
      </v-card>

      <v-card>
        <v-card-text class="text-caption text-medium-emphasis">
          {{ $t('attendance.timesheetLegend') }}
        </v-card-text>
        <div style="overflow-x: auto">
          <v-table density="compact" class="attendance-timesheet-table">
            <thead>
              <tr>
                <th class="attendance-timesheet-table__name">{{ $t('employees.fullName') }}</th>
                <th v-for="day in dayHeaders" :key="day">{{ day }}</th>
                <th>{{ $t('status.present') }}</th>
                <th>{{ $t('status.late') }}</th>
                <th>{{ $t('status.early_leave') }}</th>
                <th>{{ $t('status.absent') }}</th>
                <th>{{ $t('attendance.totalWorked') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in timesheet?.employees ?? []" :key="row.employee_id">
                <td class="attendance-timesheet-table__name">
                  <div>{{ row.full_name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ row.department ?? '—' }}</div>
                </td>
                <td
                  v-for="day in row.days"
                  :key="day.day"
                  :class="{ 'attendance-timesheet-table__weekend': day.is_weekend && !day.status }"
                  :title="day.status ? $t(`status.${day.status}`) : undefined"
                >
                  <template v-if="day.status">
                    {{ day.short_code }}<br /><span class="text-caption">{{ cellHours(day.worked_minutes) }}</span>
                  </template>
                  <template v-else-if="day.is_weekend">D</template>
                </td>
                <td>{{ row.totals.present_count }}</td>
                <td>{{ row.totals.late_count }}</td>
                <td>{{ row.totals.early_leave_count }}</td>
                <td>{{ row.totals.absent_count }}</td>
                <td>{{ formatMinutes(row.totals.total_worked_minutes) }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
        <AppEmptyState v-if="!loadingTimesheet && (timesheet?.employees.length ?? 0) === 0" icon="mdi-calendar-month-outline" />
      </v-card>
    </v-window-item>
  </v-window>

  <v-dialog
    :model-value="editTarget !== null"
    max-width="440"
    @update:model-value="(v: boolean) => !v && (editTarget = null)"
  >
    <v-card v-if="editTarget">
      <v-card-title>{{ editTarget.full_name }}</v-card-title>
      <v-card-text>
        <v-text-field
          v-model="editForm.check_in"
          type="time"
          :label="$t('attendance.checkIn')"
          :error-messages="editErrors.check_in"
        />
        <v-text-field
          v-model="editForm.check_out"
          type="time"
          :label="$t('attendance.checkOut')"
          :error-messages="editErrors.check_out"
        />
        <v-select
          v-model="editForm.status"
          :items="statusOptions"
          :label="$t('common.status')"
          clearable
          :error-messages="editErrors.status"
        />
        <v-textarea v-model="editForm.notes" :label="$t('attendance.notes')" rows="2" :error-messages="editErrors.notes" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="editTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="savingEdit" @click="saveEdit">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog
    :model-value="detailTarget !== null"
    max-width="720"
    @update:model-value="(v: boolean) => !v && (detailTarget = null)"
  >
    <v-card v-if="detailTarget">
      <v-card-title>{{ detailTarget.label }}</v-card-title>
      <v-card-text>
        <v-progress-linear v-if="loadingDetail" indeterminate class="mb-4" />
        <v-table density="compact">
          <thead>
            <tr>
              <th>{{ $t('attendance.date') }}</th>
              <th>{{ $t('attendance.checkIn') }}</th>
              <th>{{ $t('attendance.checkOut') }}</th>
              <th>{{ $t('attendance.totalWorked') }}</th>
              <th>{{ $t('employees.visitsCount') }}</th>
              <th>{{ $t('common.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in detailRows" :key="record.id">
              <td>{{ record.date }}</td>
              <td>{{ timeOnly(record.check_in) }}</td>
              <td>{{ timeOnly(record.check_out) }}</td>
              <td>{{ record.worked_minutes ? formatMinutes(record.worked_minutes) : '—' }}</td>
              <td>
                <a
                  v-if="(record.visits_count ?? 0) > 0"
                  href="#"
                  class="text-decoration-none"
                  @click.prevent="openEvents(record)"
                >{{ record.visits_count }}</a>
                <span v-else>—</span>
              </td>
              <td><AppStatusChip :status="record.status" /></td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingDetail && detailRows.length === 0" icon="mdi-calendar-blank-outline" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="detailTarget = null">{{ $t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog
    :model-value="eventsTarget !== null"
    max-width="420"
    @update:model-value="(v: boolean) => !v && (eventsTarget = null)"
  >
    <v-card v-if="eventsTarget">
      <v-card-title>{{ eventsTarget.date }}</v-card-title>
      <v-card-text>
        <v-progress-linear v-if="loadingEvents" indeterminate class="mb-4" />
        <v-timeline density="compact" side="end">
          <v-timeline-item
            v-for="event in eventsList"
            :key="event.id"
            size="x-small"
            :dot-color="event.type === 'check_in' ? 'success' : 'warning'"
          >
            <div class="text-body-2">
              {{ event.type === 'check_in' ? $t('attendance.checkIn') : $t('attendance.checkOut') }}
              — {{ timeOnly(event.occurred_at) }}
            </div>
          </v-timeline-item>
        </v-timeline>
        <AppEmptyState v-if="!loadingEvents && eventsList.length === 0" icon="mdi-history" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="eventsTarget = null">{{ $t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.attendance-timesheet-table :deep(td),
.attendance-timesheet-table :deep(th) {
  text-align: center;
  min-width: 34px;
  white-space: nowrap;
}

.attendance-timesheet-table__name {
  text-align: left !important;
  position: sticky;
  left: 0;
  background: rgb(var(--v-theme-surface));
  min-width: 160px !important;
}

.attendance-timesheet-table__weekend {
  background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
