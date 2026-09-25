<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { taskCategoryService } from '@/services/taskCategoryService'
import type { TaskCategory } from '@/types/models'

const { t } = useI18n()

const { items, total, loading, page, itemsPerPage, search, reload } = usePaginatedResource(taskCategoryService.list)

const headers = computed(() => [
  { title: t('taskCategories.name'), key: 'name' },
  { title: t('taskCategories.sortOrder'), key: 'sort_order', sortable: false },
  { title: t('taskCategories.tasksCount'), key: 'tasks_count', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const statusItems = computed(() => [
  { title: t('status.active'), value: 'active' },
  { title: t('status.inactive'), value: 'inactive' },
])

// A fixed palette rather than a free color picker: every choice stays
// readable as a chip and on the dark sidebar-adjacent tables.
const palette = ['#1E88E5', '#3949AB', '#8E24AA', '#E53935', '#F4511E', '#FB8C00', '#7CB342', '#00897B', '#6D4C41', '#757575', '#1E3A5F']

const dialogOpen = ref(false)
const editing = ref<TaskCategory | null>(null)
const form = ref({ name: '', color: palette[0], sort_order: null as number | null, status: 'active' as 'active' | 'inactive' })
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const deleteTarget = ref<TaskCategory | null>(null)
const deleting = ref(false)
const deleteBlocked = ref(false)

function openCreate() {
  editing.value = null
  form.value = { name: '', color: palette[0], sort_order: null, status: 'active' }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(category: TaskCategory) {
  editing.value = category
  form.value = { name: category.name, color: category.color, sort_order: category.sort_order, status: category.status }
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
      await taskCategoryService.update(editing.value.id, payload)
    } else {
      await taskCategoryService.create(payload)
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
    await taskCategoryService.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await reload()
  } catch (error: unknown) {
    // 409: tasks use this category — explain and point at deactivating it.
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
  <AppPageHeader :title="$t('taskCategories.title')">
    <template #actions>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ $t('common.create') }}</v-btn>
    </template>
  </AppPageHeader>

  <v-alert type="info" variant="tonal" density="compact" class="mb-4">{{ $t('taskCategories.hint') }}</v-alert>

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
    <template #item.name="{ item }">
      <span class="category-dot mr-2" :style="{ background: item.color }" />
      {{ item.name }}
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn icon="mdi-pencil-outline" variant="text" size="small" :aria-label="$t('common.edit')" @click="openEdit(item)" />
      <v-btn
        icon="mdi-delete-outline"
        variant="text"
        size="small"
        color="error"
        :aria-label="$t('common.delete')"
        @click="deleteTarget = item"
      />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-shape-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="480">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('common.create') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-text-field v-model="form.name" :label="$t('taskCategories.name')" :error-messages="formErrors.name" autofocus />

          <div class="text-body-2 text-medium-emphasis mb-2">{{ $t('taskCategories.color') }}</div>
          <div class="d-flex flex-wrap ga-2 mb-1" role="radiogroup" :aria-label="$t('taskCategories.color')">
            <button
              v-for="color in palette"
              :key="color"
              type="button"
              role="radio"
              class="color-swatch"
              :class="{ 'color-swatch--selected': form.color.toUpperCase() === color }"
              :style="{ background: color }"
              :aria-checked="form.color.toUpperCase() === color"
              :aria-label="color"
              @click="form.color = color"
            >
              <v-icon v-if="form.color.toUpperCase() === color" icon="mdi-check" size="16" color="white" />
            </button>
          </div>
          <div v-if="formErrors.color" class="text-error text-caption mb-2">{{ formErrors.color[0] }}</div>

          <v-text-field
            v-model.number="form.sort_order"
            type="number"
            min="0"
            class="mt-3"
            :label="$t('taskCategories.sortOrder')"
            :error-messages="formErrors.sort_order"
          />
          <v-select v-model="form.status" :items="statusItems" :label="$t('common.status')" :error-messages="formErrors.status" />
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
    {{ $t('taskCategories.deleteUsed') }}
  </v-snackbar>
</template>

<style scoped>
.category-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  vertical-align: middle;
}

.color-swatch {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 2px solid transparent;
  cursor: pointer;
}

.color-swatch--selected {
  border-color: rgb(var(--v-theme-on-surface));
}

.color-swatch:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}
</style>
