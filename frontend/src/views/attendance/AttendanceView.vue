<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { attendanceService } from '@/services/attendanceService'
import { useAuthStore } from '@/stores/auth'
import type { AttendanceReportRow, AttendanceStatus, TodayAttendance } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()
const canManage = computed(() => auth.can('attendance.manage'))
const tab = ref('today')

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

function formatMinutes(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return `${hours}${t('attendance.hoursShort')} ${mins}${t('attendance.minutesShort')}`
}

onMounted(() => {
  loadToday()
  loadReport()
})
</script>

<template>
  <AppPageHeader :title="$t('nav.attendance')" />

  <v-tabs v-model="tab" class="mb-4">
    <v-tab value="today">{{ $t('attendance.tabToday') }}</v-tab>
    <v-tab value="report">{{ $t('attendance.tabReport') }}</v-tab>
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
        </v-card-text>
      </v-card>

      <v-card>
        <v-table>
          <thead>
            <tr>
              <th>{{ $t('attendance.group') }}</th>
              <th>{{ $t('attendance.totalDays') }}</th>
              <th>{{ $t('attendance.totalWorked') }}</th>
              <th>{{ $t('status.late') }}</th>
              <th>{{ $t('status.absent') }}</th>
              <th>{{ $t('status.early_leave') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in reportRows" :key="row.group_id">
              <td>{{ row.label }}</td>
              <td>{{ row.total_days }}</td>
              <td>{{ formatMinutes(row.total_worked_minutes) }}</td>
              <td>{{ row.late_count }}</td>
              <td>{{ row.absent_count }}</td>
              <td>{{ row.early_leave_count }}</td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingReport && reportRows.length === 0" icon="mdi-chart-bar" />
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
</template>
