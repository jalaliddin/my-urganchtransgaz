<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { issueCategoryService } from '@/services/issueCategoryService'
import type { IssueCategory } from '@/types/models'

const { t } = useI18n()

const { items, total, loading, page, itemsPerPage, search, reload } = usePaginatedResource(issueCategoryService.list)

const headers = computed(() => [
  { title: t('issueCategories.name'), key: 'name' },
  { title: t('issueCategories.sortOrder'), key: 'sort_order', sortable: false },
  { title: t('issueCategories.issuesCount'), key: 'issues_count', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const statusItems = computed(() => [
  { title: t('status.active'), value: 'active' },
  { title: t('status.inactive'), value: 'inactive' },
])

const dialogOpen = ref(false)
const editing = ref<IssueCategory | null>(null)
const form = ref({ name: '', sort_order: null as number | null, status: 'active' as 'active' | 'inactive' })
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const deleteTarget = ref<IssueCategory | null>(null)
const deleting = ref(false)
const deleteBlocked = ref(false)

function openCreate() {
  editing.value = null
  form.value = { name: '', sort_order: null, status: 'active' }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(category: IssueCategory) {
  editing.value = category
  form.value = { name: category.name, sort_order: category.sort_order, status: category.status }
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    // An empty order on create means "put it last" — the server decides.
    const payload = { ...form.value, sort_order: form.value.sort_order ?? undefined }
    if (editing.value) {
      await issueCategoryService.update(editing.value.id, payload)
    } else {
      await issueCategoryService.create(payload)
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
    await issueCategoryService.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await reload()
  } catch (error: unknown) {
    // 409: the category is used by existing issues — explain instead of
    // failing silently, and point at deactivating it.
    if ((error as { response?: { status?: number } }).response?.status === 409) {
      deleteTarget.value = null
      deleteBlocked.value = true
    } else {
      throw error
    }
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('issueCategories.title')">
    <template #actions>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ $t('common.create') }}</v-btn>
    </template>
  </AppPageHeader>

  <v-alert type="info" variant="tonal" density="compact" class="mb-4">{{ $t('issueCategories.hint') }}</v-alert>

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
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn icon="mdi-pencil-outline" variant="text" size="small" @click="openEdit(item)" />
      <v-btn icon="mdi-delete-outline" variant="text" size="small" color="error" @click="deleteTarget = item" />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-tag-multiple-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="480">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('common.create') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-text-field
            v-model="form.name"
            :label="$t('issueCategories.name')"
            :error-messages="formErrors.name"
            autofocus
          />
          <v-text-field
            v-model.number="form.sort_order"
            type="number"
            min="0"
            :label="$t('issueCategories.sortOrder')"
            :error-messages="formErrors.sort_order"
          />
          <v-select
            v-model="form.status"
            :items="statusItems"
            :label="$t('common.status')"
            :error-messages="formErrors.status"
          />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <AppConfirmDialog
    :model-value="deleteTarget !== null"
    :loading="deleting"
    @update:model-value="(value: boolean) => !value && (deleteTarget = null)"
    @confirm="confirmDelete"
  />

  <v-snackbar v-model="deleteBlocked" color="warning" :timeout="6000">
    {{ $t('issueCategories.deleteUsed') }}
  </v-snackbar>
</template>
