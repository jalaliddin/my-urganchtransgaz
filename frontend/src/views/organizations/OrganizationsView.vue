<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { departmentService } from '@/services/departmentService'
import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { organizationService, type OrganizationPayload } from '@/services/organizationService'
import { useAuthStore } from '@/stores/auth'
import type { Department, Organization } from '@/types/models'

function emptyForm(): OrganizationPayload {
  return { parent_id: null, name: '', short_name: '', code: '', type: 'subordinate', director_name: '', address: '', phone: '', email: '' }
}

const { t } = useI18n()
const auth = useAuthStore()
const tab = ref('list')

const { items, total, loading, page, itemsPerPage, search, reload } =
  usePaginatedResource(organizationService.list)

const headers = computed(() => [
  { title: t('organizations.name'), key: 'name' },
  { title: t('organizations.code'), key: 'code' },
  { title: t('organizations.type'), key: 'type' },
  { title: t('organizations.director'), key: 'director_name' },
  { title: t('organizations.employeesCount'), key: 'employees_count', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const dialogOpen = ref(false)
const editing = ref<Organization | null>(null)
const form = ref<OrganizationPayload>(emptyForm())
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const deleteTarget = ref<Organization | null>(null)
const deleting = ref(false)

function openCreate() {
  editing.value = null
  form.value = emptyForm()
  formErrors.value = {}
  dialogOpen.value = true
  loadTree()
}

function openEdit(organization: Organization) {
  editing.value = organization
  loadTree()
  form.value = {
    parent_id: organization.parent_id,
    name: organization.name,
    short_name: organization.short_name ?? '',
    code: organization.code,
    type: organization.type,
    director_name: organization.director_name ?? '',
    address: organization.address ?? '',
    phone: organization.phone ?? '',
    email: organization.email ?? '',
  }
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    if (editing.value) {
      await organizationService.update(editing.value.id, form.value)
    } else {
      await organizationService.create(form.value)
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
    await organizationService.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await reload()
  } finally {
    deleting.value = false
  }
}

// ---- Tuzilma (structure tree): every organization, parent/child, with
// each organization's departments loaded lazily as its node expands ----
const allOrganizations = ref<Organization[]>([])
const loadingTree = ref(false)
const departmentsByOrg = ref<Record<number, Department[]>>({})
const loadingDepartmentsFor = ref<number | null>(null)

const rootOrganizations = computed(() => allOrganizations.value.filter((o) => !o.parent_id))
function childrenOf(organizationId: number): Organization[] {
  return allOrganizations.value.filter((o) => o.parent_id === organizationId)
}

async function loadTree() {
  if (allOrganizations.value.length) return
  loadingTree.value = true
  try {
    allOrganizations.value = (await organizationService.list({ per_page: 100 })).data
  } finally {
    loadingTree.value = false
  }
}

async function loadDepartments(organizationId: number) {
  if (departmentsByOrg.value[organizationId]) return
  loadingDepartmentsFor.value = organizationId
  try {
    departmentsByOrg.value[organizationId] = (
      await departmentService.list({ 'filter[organization_id]': organizationId, per_page: 100 })
    ).data
  } finally {
    loadingDepartmentsFor.value = null
  }
}

watch(tab, (value) => {
  if (value === 'tree') loadTree()
})
</script>

<template>
  <AppPageHeader :title="$t('organizations.title')">
    <template #actions>
      <v-btn
        v-if="auth.can('organizations.create')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        {{ $t('common.create') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <v-tabs v-model="tab" class="mb-4">
    <v-tab value="list">{{ $t('organizations.tabList') }}</v-tab>
    <v-tab value="tree">{{ $t('organizations.tabTree') }}</v-tab>
  </v-tabs>

  <v-window v-model="tab">
  <v-window-item value="list">
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
    <template #item.type="{ item }">
      <AppStatusChip :status="item.type" />
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn
        v-if="auth.can('organizations.update')"
        icon="mdi-pencil-outline"
        variant="text"
        size="small"
        @click="openEdit(item)"
      />
      <v-btn
        v-if="auth.can('organizations.delete')"
        icon="mdi-delete-outline"
        variant="text"
        size="small"
        color="error"
        @click="deleteTarget = item"
      />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-domain" />
    </template>
  </AppDataTable>
  </v-window-item>

  <v-window-item value="tree">
    <v-progress-linear v-if="loadingTree" indeterminate class="mb-4" />
    <v-card v-for="root in rootOrganizations" :key="root.id" class="mb-3">
      <v-list nav>
        <v-list-group :value="root.id">
          <template #activator="{ props }">
            <v-list-item
              v-bind="props"
              :title="root.name"
              :subtitle="`${root.departments_count ?? 0} ${$t('organizations.departmentsCount').toLowerCase()} · ${root.employees_count ?? 0} ${$t('organizations.employeesCount').toLowerCase()}`"
              prepend-icon="mdi-domain"
              @click="loadDepartments(root.id)"
            />
          </template>

          <v-list-item
            v-for="department in departmentsByOrg[root.id] ?? []"
            :key="`d-${department.id}`"
            :title="department.name"
            :subtitle="`${department.manager?.full_name ?? '—'} · ${department.employees_count ?? 0} ${$t('organizations.employeesCount').toLowerCase()}`"
            prepend-icon="mdi-sitemap-outline"
          />
          <v-list-item v-if="loadingDepartmentsFor === root.id" :title="$t('common.loading')" />
          <v-list-item
            v-else-if="departmentsByOrg[root.id] && departmentsByOrg[root.id].length === 0 && childrenOf(root.id).length === 0"
            :title="$t('organizations.noDepartments')"
          />

          <v-list-group v-for="child in childrenOf(root.id)" :key="child.id" :value="child.id">
            <template #activator="{ props }">
              <v-list-item
                v-bind="props"
                :title="child.name"
                :subtitle="`${child.departments_count ?? 0} ${$t('organizations.departmentsCount').toLowerCase()} · ${child.employees_count ?? 0} ${$t('organizations.employeesCount').toLowerCase()}`"
                prepend-icon="mdi-domain"
                @click="loadDepartments(child.id)"
              />
            </template>

            <v-list-item
              v-for="department in departmentsByOrg[child.id] ?? []"
              :key="`d-${department.id}`"
              :title="department.name"
              :subtitle="`${department.manager?.full_name ?? '—'} · ${department.employees_count ?? 0} ${$t('organizations.employeesCount').toLowerCase()}`"
              prepend-icon="mdi-sitemap-outline"
            />
            <v-list-item v-if="loadingDepartmentsFor === child.id" :title="$t('common.loading')" />
            <v-list-item
              v-else-if="departmentsByOrg[child.id] && departmentsByOrg[child.id].length === 0"
              :title="$t('organizations.noDepartments')"
            />
          </v-list-group>
        </v-list-group>
      </v-list>
    </v-card>
    <AppEmptyState v-if="!loadingTree && rootOrganizations.length === 0" icon="mdi-domain" />
  </v-window-item>
  </v-window>

  <v-dialog v-model="dialogOpen" max-width="560">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('common.create') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-text-field
            v-model="form.name"
            :label="$t('organizations.name')"
            :error-messages="formErrors.name"
          />
          <v-text-field
            v-model="form.short_name"
            :label="$t('organizations.shortName')"
            :error-messages="formErrors.short_name"
          />
          <v-text-field
            v-model="form.code"
            :label="$t('organizations.code')"
            :error-messages="formErrors.code"
          />
          <v-select
            v-model="form.type"
            :items="[
              { title: $t('status.central'), value: 'central' },
              { title: $t('status.subordinate'), value: 'subordinate' },
            ]"
            :label="$t('organizations.type')"
            :error-messages="formErrors.type"
          />
          <v-select
            v-model="form.parent_id"
            :items="[
              { title: $t('organizations.noParent'), value: null },
              ...allOrganizations.filter((o) => o.id !== editing?.id).map((o) => ({ title: o.name, value: o.id })),
            ]"
            :label="$t('organizations.parent')"
            :error-messages="formErrors.parent_id"
          />
          <v-text-field
            v-model="form.director_name"
            :label="$t('organizations.director')"
            :error-messages="formErrors.director_name"
          />
          <v-text-field
            v-model="form.phone"
            :label="$t('organizations.phone')"
            :error-messages="formErrors.phone"
          />
          <v-text-field
            v-model="form.email"
            :label="$t('organizations.email')"
            :error-messages="formErrors.email"
          />
          <v-textarea
            v-model="form.address"
            :label="$t('organizations.address')"
            :error-messages="formErrors.address"
            rows="2"
          />
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
