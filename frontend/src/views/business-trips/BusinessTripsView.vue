<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { businessTripService } from '@/services/businessTripService'
import { employeeService } from '@/services/employeeService'
import { useAuthStore } from '@/stores/auth'
import type { BusinessTrip, Employee } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()
const canManage = computed(() => auth.can('business_trips.manage'))

const { items, total, loading, page, itemsPerPage, reload } = usePaginatedResource(businessTripService.list)

const headers = computed(() => [
  { title: t('employees.title'), key: 'employee', sortable: false },
  { title: t('businessTrips.destination'), key: 'destination', sortable: false },
  { title: t('leaveRequests.dates'), key: 'dates', sortable: false },
  { title: t('businessTrips.orderNumber'), key: 'order_number', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const employees = ref<Employee[]>([])
onMounted(async () => {
  if (canManage.value) {
    employees.value = (await employeeService.list({ per_page: 200 })).data
  }
})
const employeeOptions = computed(() => employees.value.map((e) => ({ title: e.full_name, value: e.id })))

// ---- Create/edit dialog ----
const dialogOpen = ref(false)
const editing = ref<BusinessTrip | null>(null)
const form = reactive({
  employee_id: null as number | null,
  destination: '',
  purpose: '',
  start_date: '',
  end_date: '',
  order_number: '',
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  editing.value = null
  Object.assign(form, { employee_id: null, destination: '', purpose: '', start_date: '', end_date: '', order_number: '' })
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(trip: BusinessTrip) {
  editing.value = trip
  Object.assign(form, {
    employee_id: trip.employee_id,
    destination: trip.destination,
    purpose: trip.purpose ?? '',
    start_date: trip.start_date,
    end_date: trip.end_date,
    order_number: trip.order_number ?? '',
  })
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    const payload = {
      employee_id: form.employee_id ?? undefined,
      destination: form.destination,
      purpose: form.purpose || null,
      start_date: form.start_date,
      end_date: form.end_date,
      order_number: form.order_number || null,
    }
    if (editing.value) {
      await businessTripService.update(editing.value.id, payload)
    } else {
      await businessTripService.create(payload)
    }
    dialogOpen.value = false
    await reload()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

const actingId = ref<number | null>(null)
async function cancelTrip(trip: BusinessTrip) {
  actingId.value = trip.id
  try {
    await businessTripService.cancel(trip.id)
    await reload()
  } finally {
    actingId.value = null
  }
}
</script>

<template>
  <AppPageHeader :title="$t('nav.businessTrips')">
    <template #actions>
      <v-btn v-if="canManage" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ $t('common.create') }}</v-btn>
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
      {{ item.employee?.full_name }}
    </template>
    <template #item.dates="{ item }">
      {{ item.start_date }} — {{ item.end_date }}
    </template>
    <template #item.order_number="{ item }">
      {{ item.order_number ?? '—' }}
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <template v-if="canManage && item.status !== 'cancelled'">
        <v-btn size="small" variant="text" @click="openEdit(item)">{{ $t('common.edit') }}</v-btn>
        <v-btn size="small" color="error" variant="text" :loading="actingId === item.id" @click="cancelTrip(item)">
          {{ $t('common.cancel') }}
        </v-btn>
      </template>
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-airplane" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="520">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('businessTrips.createTrip') }}</v-card-title>
      <v-card-text>
        <v-select
          v-if="!editing"
          v-model="form.employee_id"
          :items="employeeOptions"
          :label="$t('employees.title')"
          :error-messages="formErrors.employee_id"
        />
        <v-text-field v-model="form.destination" :label="$t('businessTrips.destination')" :error-messages="formErrors.destination" />
        <v-textarea v-model="form.purpose" :label="$t('businessTrips.purpose')" rows="2" />
        <v-text-field v-model="form.start_date" type="date" :label="$t('tasks.dueDate')" :error-messages="formErrors.start_date" />
        <v-text-field v-model="form.end_date" type="date" :label="$t('exams.endDate')" :error-messages="formErrors.end_date" />
        <v-text-field v-model="form.order_number" :label="$t('businessTrips.orderNumber')" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
