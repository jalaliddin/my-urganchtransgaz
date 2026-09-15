<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { departmentService } from '@/services/departmentService'
import { employeeService } from '@/services/employeeService'
import { organizationService } from '@/services/organizationService'
import { useAuthStore } from '@/stores/auth'
import type { Department, Employee, Organization } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()

const { items, total, loading, page, itemsPerPage, search, reload } =
  usePaginatedResource(employeeService.list)

const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])

onMounted(async () => {
  const result = await organizationService.list({ per_page: 100 })
  organizations.value = result.data
})

const headers = computed(() => [
  { title: t('employees.employeeNumber'), key: 'employee_number' },
  { title: t('employees.fullName'), key: 'full_name' },
  { title: t('employees.organization'), key: 'organization.name', sortable: false },
  { title: t('employees.department'), key: 'department.name', sortable: false },
  { title: t('employees.phone'), key: 'phone', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const dialogOpen = ref(false)
const editing = ref<Employee | null>(null)
const form = ref({
  organization_id: null as number | null,
  department_id: null as number | null,
  employee_number: '',
  first_name: '',
  last_name: '',
  middle_name: '',
  phone: '',
  corporate_email: '',
  create_account: false,
  username: '',
  password: '',
  role: 'employee',
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const deleteTarget = ref<Employee | null>(null)
const deleting = ref(false)

const assignableRoles = [
  'employee', 'manager', 'department-manager', 'hr', 'safety-manager', 'technical-policy',
]

watch(
  () => form.value.organization_id,
  async (organizationId) => {
    if (!organizationId) {
      departments.value = []
      return
    }
    const result = await departmentService.list({ per_page: 100, 'filter[organization_id]': organizationId })
    departments.value = result.data
  },
)

function openCreate() {
  editing.value = null
  form.value = {
    organization_id: organizations.value[0]?.id ?? null,
    department_id: null,
    employee_number: '',
    first_name: '',
    last_name: '',
    middle_name: '',
    phone: '',
    corporate_email: '',
    create_account: false,
    username: '',
    password: '',
    role: 'employee',
  }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(employee: Employee) {
  editing.value = employee
  form.value = {
    organization_id: employee.organization_id,
    department_id: employee.department_id,
    employee_number: employee.employee_number,
    first_name: employee.first_name,
    last_name: employee.last_name,
    middle_name: employee.middle_name ?? '',
    phone: employee.phone ?? '',
    corporate_email: employee.corporate_email ?? '',
    create_account: false,
    username: '',
    password: '',
    role: 'employee',
  }
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    if (editing.value) {
      const { organization_id, department_id, employee_number, first_name, last_name, middle_name, phone, corporate_email } = form.value
      await employeeService.update(editing.value.id, {
        organization_id,
        department_id,
        employee_number,
        first_name,
        last_name,
        middle_name,
        phone,
        corporate_email,
      })
    } else {
      await employeeService.create(form.value)
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

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await employeeService.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await reload()
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('employees.title')">
    <template #actions>
      <v-btn
        v-if="auth.can('employees.create')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        {{ $t('common.create') }}
      </v-btn>
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
    @update:search="(v: string) => (search = v)"
  >
    <template #item.organization.name="{ item }">
      {{ item.organization?.name ?? '—' }}
    </template>
    <template #item.department.name="{ item }">
      {{ item.department?.name ?? '—' }}
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn
        v-if="auth.can('employees.update')"
        icon="mdi-pencil-outline"
        variant="text"
        size="small"
        @click="openEdit(item)"
      />
      <v-btn
        v-if="auth.can('employees.delete')"
        icon="mdi-delete-outline"
        variant="text"
        size="small"
        color="error"
        @click="deleteTarget = item"
      />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-account-group-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="640">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('common.create') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-row>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.organization_id"
                :items="organizations.map((o) => ({ title: o.name, value: o.id }))"
                :label="$t('employees.organization')"
                :error-messages="formErrors.organization_id"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.department_id"
                :items="departments.map((d) => ({ title: d.name, value: d.id }))"
                :label="$t('employees.department')"
                :error-messages="formErrors.department_id"
                clearable
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.employee_number"
                :label="$t('employees.employeeNumber')"
                :error-messages="formErrors.employee_number"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.phone"
                :label="$t('employees.phone')"
                :error-messages="formErrors.phone"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.last_name"
                :label="$t('employees.lastName')"
                :error-messages="formErrors.last_name"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.first_name"
                :label="$t('employees.firstName')"
                :error-messages="formErrors.first_name"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.middle_name"
                :label="$t('employees.middleName')"
                :error-messages="formErrors.middle_name"
              />
            </v-col>

            <template v-if="!editing">
              <v-col cols="12">
                <v-switch v-model="form.create_account" :label="$t('employees.createAccount')" color="primary" hide-details />
              </v-col>
              <template v-if="form.create_account">
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.username"
                    :label="$t('employees.username')"
                    :error-messages="formErrors.username"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.corporate_email"
                    :label="$t('employees.corporateEmail')"
                    :error-messages="formErrors.corporate_email"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.password"
                    :label="$t('auth.password')"
                    type="password"
                    :error-messages="formErrors.password"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.role"
                    :items="assignableRoles"
                    :label="$t('employees.role')"
                    :error-messages="formErrors.role"
                  />
                </v-col>
              </template>
            </template>
          </v-row>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">
          {{ $t('common.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <AppConfirmDialog
    :model-value="deleteTarget !== null"
    :loading="deleting"
    @update:model-value="(value: boolean) => !value && (deleteTarget = null)"
    @confirm="confirmDelete"
  />
</template>
