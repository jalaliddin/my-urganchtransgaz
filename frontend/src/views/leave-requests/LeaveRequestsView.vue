<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { leaveRequestService } from '@/services/leaveRequestService'
import { useAuthStore } from '@/stores/auth'
import type { LeaveRequest, LeaveRequestType } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()

const { items, total, loading, page, itemsPerPage, reload } = usePaginatedResource(leaveRequestService.list)

const myEmployeeId = computed(() => auth.user?.employee?.id)

const headers = computed(() => [
  { title: t('employees.title'), key: 'employee', sortable: false },
  { title: t('leaveRequests.type'), key: 'type', sortable: false },
  { title: t('leaveRequests.dates'), key: 'dates', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

function isMine(item: LeaveRequest): boolean {
  return item.employee_id === myEmployeeId.value
}

function canCancel(item: LeaveRequest): boolean {
  return isMine(item) && ['pending', 'department_approved'].includes(item.status)
}

function canDepartmentReview(item: LeaveRequest): boolean {
  return !isMine(item) && auth.can('leave_requests.review') && item.status === 'pending'
}

function canApprove(item: LeaveRequest): boolean {
  return !isMine(item) && auth.can('leave_requests.approve') && item.status === 'department_approved'
}

// ---- Create dialog ----
const dialogOpen = ref(false)
const form = reactive({
  type: 'vacation' as LeaveRequestType,
  start_date: '',
  end_date: '',
  reason: '',
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  Object.assign(form, { type: 'vacation', start_date: '', end_date: '', reason: '' })
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    await leaveRequestService.create({
      type: form.type,
      start_date: form.start_date,
      end_date: form.end_date,
      reason: form.reason || null,
    })
    dialogOpen.value = false
    await reload()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

// ---- Actions ----
const actingId = ref<number | null>(null)

async function cancel(item: LeaveRequest) {
  actingId.value = item.id
  try {
    await leaveRequestService.cancel(item.id)
    await reload()
  } finally {
    actingId.value = null
  }
}

async function departmentApprove(item: LeaveRequest) {
  actingId.value = item.id
  try {
    await leaveRequestService.departmentApprove(item.id)
    await reload()
  } finally {
    actingId.value = null
  }
}

async function approve(item: LeaveRequest) {
  actingId.value = item.id
  try {
    await leaveRequestService.approve(item.id)
    await reload()
  } finally {
    actingId.value = null
  }
}

const rejectTarget = ref<LeaveRequest | null>(null)
const rejectReason = ref('')
const rejecting = ref(false)

async function submitReject() {
  if (!rejectTarget.value) return
  rejecting.value = true
  try {
    if (rejectTarget.value.status === 'pending') {
      await leaveRequestService.departmentReject(rejectTarget.value.id, rejectReason.value)
    } else {
      await leaveRequestService.reject(rejectTarget.value.id, rejectReason.value)
    }
    rejectTarget.value = null
    rejectReason.value = ''
    await reload()
  } finally {
    rejecting.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('nav.leaveRequests')">
    <template #actions>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ $t('common.create') }}</v-btn>
    </template>
  </AppPageHeader>

  <AppDataTable
    :headers="headers"
    :items="items"
    :items-length="total"
    :loading="loading"
    :page="page"
    :items-per-page="itemsPerPage"
    @update:page="(v: number) => (page = v)"
    @update:items-per-page="(v: number) => (itemsPerPage = v)"
  >
    <template #item.employee="{ item }">
      {{ isMine(item) ? $t('leaveRequests.me') : item.employee?.full_name }}
    </template>
    <template #item.type="{ item }">
      {{ $t(`leaveRequests.types.${item.type}`) }}
    </template>
    <template #item.dates="{ item }">
      {{ item.start_date }} — {{ item.end_date }}
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn v-if="canCancel(item)" size="small" variant="text" :loading="actingId === item.id" @click="cancel(item)">
        {{ $t('common.cancel') }}
      </v-btn>
      <v-btn
        v-if="canDepartmentReview(item)"
        size="small"
        color="success"
        variant="text"
        :loading="actingId === item.id"
        @click="departmentApprove(item)"
      >
        {{ $t('leaveRequests.approve') }}
      </v-btn>
      <v-btn v-if="canApprove(item)" size="small" color="success" variant="text" :loading="actingId === item.id" @click="approve(item)">
        {{ $t('leaveRequests.approve') }}
      </v-btn>
      <v-btn
        v-if="canDepartmentReview(item) || canApprove(item)"
        size="small"
        color="error"
        variant="text"
        @click="rejectTarget = item"
      >
        {{ $t('leaveRequests.reject') }}
      </v-btn>
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-calendar-remove-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="480">
    <v-card>
      <v-card-title>{{ $t('leaveRequests.createRequest') }}</v-card-title>
      <v-card-text>
        <v-select
          v-model="form.type"
          :items="[
            { title: $t('leaveRequests.types.vacation'), value: 'vacation' },
            { title: $t('leaveRequests.types.business_trip'), value: 'business_trip' },
            { title: $t('leaveRequests.types.sick_leave'), value: 'sick_leave' },
            { title: $t('leaveRequests.types.other'), value: 'other' },
          ]"
          :label="$t('leaveRequests.type')"
        />
        <v-text-field v-model="form.start_date" type="date" :label="$t('tasks.dueDate')" :error-messages="formErrors.start_date" />
        <v-text-field v-model="form.end_date" type="date" :label="$t('exams.endDate')" :error-messages="formErrors.end_date" />
        <v-textarea v-model="form.reason" :label="$t('leaveRequests.reason')" rows="3" :error-messages="formErrors.reason" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog :model-value="rejectTarget !== null" max-width="440" @update:model-value="(v: boolean) => !v && (rejectTarget = null)">
    <v-card>
      <v-card-title>{{ $t('leaveRequests.reject') }}</v-card-title>
      <v-card-text>
        <v-textarea v-model="rejectReason" :label="$t('documents.rejectReason')" rows="3" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="rejectTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="error" variant="flat" :loading="rejecting" @click="submitReject">{{ $t('leaveRequests.reject') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
