<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { organizationService, type OrganizationPayload } from '@/services/organizationService'
import { useAuthStore } from '@/stores/auth'
import type { Organization } from '@/types/models'

function emptyForm(): OrganizationPayload {
  return { name: '', short_name: '', code: '', type: 'subordinate', director_name: '', address: '', phone: '', email: '' }
}

const { t } = useI18n()
const auth = useAuthStore()

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
}

function openEdit(organization: Organization) {
  editing.value = organization
  form.value = {
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
