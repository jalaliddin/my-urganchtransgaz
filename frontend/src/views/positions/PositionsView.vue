<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { organizationService } from '@/services/organizationService'
import { positionService } from '@/services/positionService'
import { useAuthStore } from '@/stores/auth'
import type { Organization, Position } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()

const { items, total, loading, page, itemsPerPage, search, reload } =
  usePaginatedResource(positionService.list)

const organizations = ref<Organization[]>([])
onMounted(async () => {
  const result = await organizationService.list({ per_page: 100 })
  organizations.value = result.data
})

const headers = computed(() => [
  { title: t('positions.positionTitle'), key: 'title' },
  { title: t('positions.code'), key: 'code' },
  { title: t('positions.organization'), key: 'organization.name', sortable: false },
  { title: t('departments.employeesCount'), key: 'employees_count', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const dialogOpen = ref(false)
const editing = ref<Position | null>(null)
const form = ref({ organization_id: null as number | null, title: '', code: '' })
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const deleteTarget = ref<Position | null>(null)
const deleting = ref(false)

function openCreate() {
  editing.value = null
  form.value = { organization_id: organizations.value[0]?.id ?? null, title: '', code: '' }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(position: Position) {
  editing.value = position
  form.value = {
    organization_id: position.organization_id,
    title: position.title,
    code: position.code ?? '',
  }
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    if (editing.value) {
      await positionService.update(editing.value.id, form.value)
    } else {
      await positionService.create(form.value)
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
    await positionService.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await reload()
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('positions.title')">
    <template #actions>
      <v-btn
        v-if="auth.can('positions.create')"
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
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn
        v-if="auth.can('positions.update')"
        icon="mdi-pencil-outline"
        variant="text"
        size="small"
        @click="openEdit(item)"
      />
      <v-btn
        v-if="auth.can('positions.delete')"
        icon="mdi-delete-outline"
        variant="text"
        size="small"
        color="error"
        @click="deleteTarget = item"
      />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-badge-account-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="480">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('common.create') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-select
            v-model="form.organization_id"
            :items="organizations.map((o) => ({ title: o.name, value: o.id }))"
            :label="$t('positions.organization')"
            :error-messages="formErrors.organization_id"
          />
          <v-text-field
            v-model="form.title"
            :label="$t('positions.positionTitle')"
            :error-messages="formErrors.title"
          />
          <v-text-field
            v-model="form.code"
            :label="$t('positions.code')"
            :error-messages="formErrors.code"
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
