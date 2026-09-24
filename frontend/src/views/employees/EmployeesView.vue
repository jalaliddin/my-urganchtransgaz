<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { departmentService } from '@/services/departmentService'
import { employeeImportService, employeeService } from '@/services/employeeService'
import { organizationService } from '@/services/organizationService'
import { positionService } from '@/services/positionService'
import { useAuthStore } from '@/stores/auth'
import type { Department, Employee, EmployeeStatus, ImportResult, Organization, Position } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const employeeStatuses: EmployeeStatus[] = ['active', 'vacation', 'business_trip', 'sick_leave', 'inactive', 'terminated']

const { items, total, loading, page, itemsPerPage, search, reload } =
  usePaginatedResource(employeeService.list)

const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
const positions = ref<Position[]>([])

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
  position_id: null as number | null,
  employee_number: '',
  dahua_person_id: '',
  first_name: '',
  last_name: '',
  middle_name: '',
  phone: '',
  corporate_email: '',
  status: 'active' as EmployeeStatus,
  create_account: false,
  username: '',
  password: '',
  role: 'employee',
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const deleteTarget = ref<Employee | null>(null)
const deleting = ref(false)

const assignableRoles = computed(() => auth.user?.assignable_roles ?? [])

watch(
  () => form.value.organization_id,
  async (organizationId) => {
    if (!organizationId) {
      departments.value = []
      positions.value = []
      return
    }
    const [departmentResult, positionResult] = await Promise.all([
      departmentService.list({ per_page: 100, 'filter[organization_id]': organizationId }),
      positionService.list({ per_page: 100, 'filter[organization_id]': organizationId }),
    ])
    departments.value = departmentResult.data
    positions.value = positionResult.data
  },
)

function openCreate() {
  editing.value = null
  form.value = {
    organization_id: organizations.value[0]?.id ?? null,
    department_id: null,
    position_id: null,
    employee_number: '',
    dahua_person_id: '',
    first_name: '',
    last_name: '',
    middle_name: '',
    phone: '',
    corporate_email: '',
    status: 'active' as EmployeeStatus,
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
    position_id: employee.position_id,
    employee_number: employee.employee_number,
    dahua_person_id: employee.dahua_person_id ?? '',
    first_name: employee.first_name,
    last_name: employee.last_name,
    middle_name: employee.middle_name ?? '',
    phone: employee.phone ?? '',
    corporate_email: employee.corporate_email ?? '',
    status: employee.status,
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
      const { organization_id, department_id, position_id, employee_number, dahua_person_id, first_name, last_name, middle_name, phone, corporate_email, status } = form.value
      await employeeService.update(editing.value.id, {
        organization_id,
        department_id,
        position_id,
        employee_number,
        // An empty field means "not enrolled on a device", i.e. null — not
        // the literal empty string, which would collide with every other
        // not-yet-enrolled employee under the column's unique constraint.
        dahua_person_id: dahua_person_id || null,
        first_name,
        last_name,
        middle_name,
        phone,
        corporate_email,
        status,
      })
    } else {
      await employeeService.create({ ...form.value, dahua_person_id: form.value.dahua_person_id || null })
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

function openDetail(employee: Employee) {
  router.push({ name: 'employee-detail', params: { id: employee.id } })
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

// ---- Export ----
const exporting = ref(false)
async function exportAs(format: 'csv' | 'xlsx' | 'pdf') {
  exporting.value = true
  try {
    await employeeService.export(format, { 'filter[search]': search.value || undefined })
  } finally {
    exporting.value = false
  }
}

// ---- Role change ----
const roleTarget = ref<Employee | null>(null)
const roleForm = ref('employee')
const changingRole = ref(false)

function openRoleDialog(employee: Employee) {
  roleTarget.value = employee
  roleForm.value = 'employee'
}

async function submitRoleChange() {
  if (!roleTarget.value) return
  changingRole.value = true
  try {
    await employeeService.updateRole(roleTarget.value.id, roleForm.value)
    roleTarget.value = null
    await reload()
  } finally {
    changingRole.value = false
  }
}

// ---- Open an account for an employee who doesn't have one yet ----
const accountTarget = ref<Employee | null>(null)
const accountForm = ref({ username: '', corporate_email: '', password: '', role: 'employee' })
const accountErrors = ref<Record<string, string[]>>({})
const creatingAccount = ref(false)

function openAccountDialog(employee: Employee) {
  accountTarget.value = employee
  accountForm.value = { username: '', corporate_email: employee.corporate_email ?? '', password: '', role: 'employee' }
  accountErrors.value = {}
}

async function submitCreateAccount() {
  if (!accountTarget.value) return
  creatingAccount.value = true
  accountErrors.value = {}
  try {
    await employeeService.createAccount(accountTarget.value.id, accountForm.value)
    accountTarget.value = null
    await reload()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    accountErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    creatingAccount.value = false
  }
}

// ---- Reset an existing account's password ----
const passwordTarget = ref<Employee | null>(null)
const passwordForm = ref({ password: '', password_confirmation: '' })
const passwordErrors = ref<Record<string, string[]>>({})
const resettingPassword = ref(false)

function openPasswordDialog(employee: Employee) {
  passwordTarget.value = employee
  passwordForm.value = { password: '', password_confirmation: '' }
  passwordErrors.value = {}
}

async function submitResetPassword() {
  if (!passwordTarget.value) return
  resettingPassword.value = true
  passwordErrors.value = {}
  try {
    await employeeService.resetPassword(passwordTarget.value.id, passwordForm.value)
    passwordTarget.value = null
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    passwordErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    resettingPassword.value = false
  }
}

// ---- Import ----
const importDialogOpen = ref(false)
const importFile = ref<File | null>(null)
const importPreview = ref<ImportResult | null>(null)
const importing = ref(false)

function openImportDialog() {
  importFile.value = null
  importPreview.value = null
  importDialogOpen.value = true
}

async function previewImport() {
  if (!importFile.value) return
  importing.value = true
  try {
    importPreview.value = await employeeImportService.upload(importFile.value, true)
  } finally {
    importing.value = false
  }
}

async function confirmImport() {
  if (!importFile.value) return
  importing.value = true
  try {
    importPreview.value = await employeeImportService.upload(importFile.value, false)
    await reload()
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('employees.title')">
    <template #actions>
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download" :loading="exporting">{{ $t('export.export') }}</v-btn>
        </template>
        <v-list>
          <v-list-item title="CSV" @click="exportAs('csv')" />
          <v-list-item title="Excel" @click="exportAs('xlsx')" />
          <v-list-item title="PDF" @click="exportAs('pdf')" />
        </v-list>
      </v-menu>
      <v-btn v-if="auth.can('employees.create')" variant="tonal" prepend-icon="mdi-upload" @click="openImportDialog">
        {{ $t('import.import') }}
      </v-btn>
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
    <template #item.full_name="{ item }">
      <a href="#" class="text-decoration-none" @click.prevent="openDetail(item)">{{ item.full_name }}</a>
    </template>
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
        v-if="auth.can('users.update') && item.user_id"
        icon="mdi-account-key-outline"
        variant="text"
        size="small"
        :title="$t('employees.changeRole')"
        @click="openRoleDialog(item)"
      />
      <v-btn
        v-if="auth.can('users.update') && item.user_id"
        icon="mdi-lock-reset"
        variant="text"
        size="small"
        :title="$t('employees.resetPassword')"
        @click="openPasswordDialog(item)"
      />
      <v-btn
        v-if="auth.can('users.update') && !item.user_id"
        icon="mdi-account-plus-outline"
        variant="text"
        size="small"
        :title="$t('employees.openAccount')"
        @click="openAccountDialog(item)"
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
              <v-select
                v-model="form.position_id"
                :items="positions.map((p) => ({ title: p.title, value: p.id }))"
                :label="$t('employees.position')"
                :error-messages="formErrors.position_id"
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
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.dahua_person_id"
                :label="$t('employees.dahuaPersonId')"
                :hint="$t('employees.dahuaPersonIdHint')"
                persistent-hint
                :error-messages="formErrors.dahua_person_id"
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

            <v-col v-if="editing" cols="12" sm="6">
              <v-select
                v-model="form.status"
                :items="employeeStatuses.map((s) => ({ title: $t(`status.${s}`), value: s }))"
                :label="$t('employees.status')"
                :error-messages="formErrors.status"
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

  <v-dialog :model-value="roleTarget !== null" max-width="420" @update:model-value="(v: boolean) => !v && (roleTarget = null)">
    <v-card>
      <v-card-title>{{ $t('employees.changeRole') }}</v-card-title>
      <v-card-text>
        <v-select v-model="roleForm" :items="assignableRoles" :label="$t('employees.role')" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="roleTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="changingRole" @click="submitRoleChange">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog :model-value="accountTarget !== null" max-width="480" @update:model-value="(v: boolean) => !v && (accountTarget = null)">
    <v-card v-if="accountTarget">
      <v-card-title>{{ $t('employees.openAccount') }}</v-card-title>
      <v-card-subtitle>{{ accountTarget.full_name }}</v-card-subtitle>
      <v-card-text>
        <v-form @submit.prevent="submitCreateAccount">
          <v-text-field
            v-model="accountForm.username"
            :label="$t('employees.username')"
            :error-messages="accountErrors.username"
          />
          <v-text-field
            v-model="accountForm.corporate_email"
            :label="$t('employees.corporateEmail')"
            :error-messages="accountErrors.corporate_email"
          />
          <v-text-field
            v-model="accountForm.password"
            :label="$t('auth.password')"
            type="password"
            :error-messages="accountErrors.password"
          />
          <v-select
            v-model="accountForm.role"
            :items="assignableRoles"
            :label="$t('employees.role')"
            :error-messages="accountErrors.role"
          />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="accountTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="creatingAccount" @click="submitCreateAccount">
          {{ $t('common.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog :model-value="passwordTarget !== null" max-width="420" @update:model-value="(v: boolean) => !v && (passwordTarget = null)">
    <v-card v-if="passwordTarget">
      <v-card-title>{{ $t('employees.resetPassword') }}</v-card-title>
      <v-card-subtitle>{{ passwordTarget.full_name }}</v-card-subtitle>
      <v-card-text>
        <v-form @submit.prevent="submitResetPassword">
          <v-text-field
            v-model="passwordForm.password"
            :label="$t('employees.newPassword')"
            type="password"
            :error-messages="passwordErrors.password"
          />
          <v-text-field
            v-model="passwordForm.password_confirmation"
            :label="$t('employees.confirmPassword')"
            type="password"
          />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="passwordTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="resettingPassword" @click="submitResetPassword">
          {{ $t('common.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="importDialogOpen" max-width="640">
    <v-card>
      <v-card-title>{{ $t('import.importEmployees') }}</v-card-title>
      <v-card-text>
        <v-btn variant="text" prepend-icon="mdi-download" class="mb-4" @click="employeeImportService.downloadTemplate()">
          {{ $t('import.downloadTemplate') }}
        </v-btn>

        <AppFileUpload v-model="importFile" :label="$t('import.file')" accept=".csv,.xlsx,.xls" />

        <div v-if="importPreview" class="mt-4">
          <p class="text-body-2 mb-2">
            {{ importPreview.dry_run ? $t('import.previewSummary', { count: importPreview.imported_count }) : $t('import.importedSummary', { count: importPreview.imported_count }) }}
          </p>

          <v-alert v-if="importPreview.invalid.length" type="error" variant="tonal" density="compact" class="mb-2">
            <div v-for="(row, index) in importPreview.invalid" :key="index" class="text-caption">
              {{ $t('import.row') }} {{ row.row }}: {{ (row.errors ?? []).join(', ') || row.error }}
            </div>
          </v-alert>

          <v-alert v-if="importPreview.skipped.length" type="warning" variant="tonal" density="compact">
            <div v-for="(row, index) in importPreview.skipped" :key="index" class="text-caption">
              {{ $t('import.row') }} {{ row.row }}: {{ row.error }}
            </div>
          </v-alert>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="importDialogOpen = false">{{ $t('common.close') }}</v-btn>
        <v-btn variant="tonal" :disabled="!importFile" :loading="importing" @click="previewImport">
          {{ $t('import.preview') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :disabled="!importFile || !importPreview?.dry_run"
          :loading="importing"
          @click="confirmImport"
        >
          {{ $t('import.confirmImport') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
