<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { absenceService } from '@/services/absenceService'
import { employeeService } from '@/services/employeeService'
import type { AbsenceType, Employee, EmployeeAbsence, LeaveBalance } from '@/types/models'
import { ABSENCE_TYPES, inclusiveDays } from '@/utils/absences'

const props = defineProps<{
  modelValue: boolean
  /** The record being edited; null to create a new one. */
  absence?: EmployeeAbsence | null
  /** Fixes the employee when creating from their own page. */
  employee?: Employee | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [absence: EmployeeAbsence]
}>()

const { t } = useI18n()

const emptyForm = () => ({
  employee_id: null as number | null,
  type: 'annual_leave' as AbsenceType,
  start_date: '',
  end_date: '',
  document_number: '',
  document_date: '',
  destination: '',
  notes: '',
  file: null as File | null,
})

const form = ref(emptyForm())
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

const isEdit = computed(() => Boolean(props.absence))
const fixedEmployee = computed(() => props.absence?.employee ?? props.employee ?? null)

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    errors.value = {}
    const source = props.absence
    form.value = source
      ? {
          employee_id: source.employee_id,
          type: source.type,
          start_date: source.start_date,
          end_date: source.end_date,
          document_number: source.document_number ?? '',
          document_date: source.document_date ?? '',
          destination: source.destination ?? '',
          notes: source.notes ?? '',
          file: null,
        }
      : { ...emptyForm(), employee_id: props.employee?.id ?? null }
    employeeOptions.value = fixedEmployee.value ? [fixedEmployee.value] : []
  },
)

const typeItems = computed(() => ABSENCE_TYPES.map((type) => ({ title: t(`absences.types.${type}`), value: type })))
const dayCount = computed(() => inclusiveDays(form.value.start_date, form.value.end_date))

const employeeOptions = ref<Employee[]>([])
const employeeSearch = ref('')
const searchingEmployees = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | undefined

function employeeTitle(employee: Employee): string {
  return `${employee.full_name} (${employee.employee_number})`
}

const selectedEmployee = computed(() => employeeOptions.value.find((e) => e.id === form.value.employee_id) ?? null)

watch(employeeSearch, (term) => {
  clearTimeout(searchTimer)
  // Picking an option writes its title back into the search box; that
  // isn't a new query, and re-searching it would drop the pick from the list.
  const picked = selectedEmployee.value
  if (!term || term.length < 2 || fixedEmployee.value || (picked && term === employeeTitle(picked))) return
  searchTimer = setTimeout(async () => {
    searchingEmployees.value = true
    try {
      const result = await employeeService.list({ 'filter[search]': term, per_page: 20 })
      const current = selectedEmployee.value
      employeeOptions.value = current && !result.data.some((e) => e.id === current.id) ? [current, ...result.data] : result.data
    } finally {
      searchingEmployees.value = false
    }
  }, 300)
})

/**
 * Warns before saving annual leave that would go past the entitlement —
 * a warning, not a block, since carried-over days and HR decisions the
 * app doesn't model can legitimately exceed it.
 */
const balance = ref<LeaveBalance | null>(null)
const balanceYear = computed(() => (form.value.start_date ? Number(form.value.start_date.slice(0, 4)) : null))

watch(
  () => [form.value.employee_id, form.value.type, balanceYear.value] as const,
  async ([employeeId, type, year]) => {
    balance.value = null
    if (!employeeId || type !== 'annual_leave' || !year) return
    try {
      balance.value = await absenceService.balance(employeeId, year)
    } catch {
      balance.value = null
    }
  },
)

const overBy = computed(() => {
  if (!balance.value) return 0
  const original = props.absence
  const alreadyCounted =
    original && original.type === 'annual_leave' && original.start_date.slice(0, 4) === String(balance.value.year)
      ? original.days
      : 0
  return balance.value.used_days - alreadyCounted + dayCount.value - balance.value.entitlement_days
})

function close() {
  emit('update:modelValue', false)
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    type: form.value.type,
    start_date: form.value.start_date,
    end_date: form.value.end_date,
    document_number: form.value.document_number,
    document_date: form.value.document_date,
    destination: form.value.type === 'business_trip' ? form.value.destination : '',
    notes: form.value.notes,
    file: form.value.file,
  }
  try {
    const saved = props.absence
      ? await absenceService.update(props.absence.id, payload)
      : await absenceService.create({ ...payload, employee_id: form.value.employee_id ?? undefined })
    emit('saved', saved)
    close()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    errors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="560" @update:model-value="(v: boolean) => emit('update:modelValue', v)">
    <v-card>
      <v-card-title>{{ isEdit ? $t('absences.edit') : $t('absences.create') }}</v-card-title>
      <v-card-text>
        <v-text-field
          v-if="fixedEmployee"
          :model-value="fixedEmployee.full_name"
          :label="$t('absences.employee')"
          prepend-inner-icon="mdi-account-outline"
          readonly
        />
        <v-autocomplete
          v-else
          v-model="form.employee_id"
          v-model:search="employeeSearch"
          :items="employeeOptions"
          :item-title="employeeTitle"
          item-value="id"
          :loading="searchingEmployees"
          :label="$t('absences.employee')"
          :placeholder="$t('absences.searchEmployee')"
          :error-messages="errors.employee_id"
          prepend-inner-icon="mdi-account-search-outline"
          no-filter
          autofocus
        />

        <v-select v-model="form.type" :items="typeItems" :label="$t('absences.type')" :error-messages="errors.type" />

        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.start_date" type="date" :label="$t('absences.startDate')" :error-messages="errors.start_date" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="form.end_date"
              type="date"
              :min="form.start_date || undefined"
              :label="$t('absences.endDate')"
              :error-messages="errors.end_date"
              :hint="dayCount ? $t('absences.days', { count: dayCount }) : undefined"
              persistent-hint
            />
          </v-col>
        </v-row>

        <v-alert
          v-if="balance"
          :type="overBy > 0 ? 'warning' : 'info'"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{
            $t('absences.balanceHint', {
              year: balance.year,
              entitlement: balance.entitlement_days,
              used: balance.used_days,
              remaining: balance.remaining_days,
            })
          }}
          <template v-if="overBy > 0"><br />{{ $t('absences.overEntitlement', { over: overBy }) }}</template>
        </v-alert>

        <v-text-field
          v-if="form.type === 'business_trip'"
          v-model="form.destination"
          :label="$t('absences.destination')"
          :error-messages="errors.destination"
          prepend-inner-icon="mdi-map-marker-outline"
        />

        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.document_number" :label="$t('absences.documentNumber')" :error-messages="errors.document_number" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.document_date" type="date" :label="$t('absences.documentDate')" :error-messages="errors.document_date" />
          </v-col>
        </v-row>

        <v-textarea v-model="form.notes" :label="$t('absences.notes')" rows="2" auto-grow :error-messages="errors.notes" />

        <AppFileUpload
          v-model="form.file"
          accept="application/pdf,image/jpeg,image/png"
          :label="$t('absences.file')"
          :error-messages="errors.file"
        />
        <div v-if="absence?.file_name && !form.file" class="text-body-small text-medium-emphasis mt-n2">
          {{ $t('absences.currentFile', { name: absence.file_name }) }}
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="close">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
