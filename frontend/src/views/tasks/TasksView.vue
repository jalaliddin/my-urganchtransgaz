<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { employeeService } from '@/services/employeeService'
import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import type { Employee, TaskPriority } from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const canCreate = computed(() => auth.can('tasks.create'))

const { items, total, loading, page, itemsPerPage, search, reload } = usePaginatedResource(taskService.list)

const employees = ref<Employee[]>([])
onMounted(async () => {
  if (canCreate.value) {
    const result = await employeeService.list({ per_page: 200 })
    employees.value = result.data
  }
})

const employeeOptions = computed(() => employees.value.map((e) => ({ title: e.full_name, value: e.id })))

const headers = computed(() => [
  { title: t('tasks.taskTitle'), key: 'title' },
  { title: t('tasks.assignees'), key: 'assignees', sortable: false },
  { title: t('tasks.priority'), key: 'priority', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('tasks.dueDate'), key: 'due_date', sortable: false },
])

const dialogOpen = ref(false)
const form = ref({
  title: '',
  description: '',
  priority: 'normal' as TaskPriority,
  due_date: '',
  assignee_ids: [] as number[],
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  form.value = { title: '', description: '', priority: 'normal', due_date: '', assignee_ids: [] }
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    await taskService.create({
      title: form.value.title,
      description: form.value.description || null,
      priority: form.value.priority,
      due_date: form.value.due_date || null,
      assignee_ids: form.value.assignee_ids,
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

function openTask(taskId: number) {
  router.push({ name: 'task-detail', params: { id: taskId } })
}
</script>

<template>
  <AppPageHeader :title="$t('nav.tasks')">
    <template #actions>
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">
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
    <template #item.title="{ item }">
      <a href="#" class="text-decoration-none" @click.prevent="openTask(item.id)">{{ item.title }}</a>
    </template>
    <template #item.assignees="{ item }">
      {{ (item.assignees ?? []).map((a: Employee) => a.full_name).join(', ') || '—' }}
    </template>
    <template #item.priority="{ item }">
      <AppStatusChip :status="item.priority" />
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.due_date="{ item }">
      {{ item.due_date ?? '—' }}
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-clipboard-check-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="520">
    <v-card>
      <v-card-title>{{ $t('tasks.createTask') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.title" :label="$t('tasks.taskTitle')" :error-messages="formErrors.title" />
        <v-textarea v-model="form.description" :label="$t('tasks.description')" rows="3" :error-messages="formErrors.description" />
        <v-select
          v-model="form.priority"
          :items="[
            { title: $t('status.low'), value: 'low' },
            { title: $t('status.normal'), value: 'normal' },
            { title: $t('status.high'), value: 'high' },
            { title: $t('status.urgent'), value: 'urgent' },
          ]"
          :label="$t('tasks.priority')"
        />
        <v-text-field v-model="form.due_date" type="date" :label="$t('tasks.dueDate')" :error-messages="formErrors.due_date" />
        <v-autocomplete
          v-model="form.assignee_ids"
          :items="employeeOptions"
          :label="$t('tasks.assignees')"
          multiple
          chips
          closable-chips
          :error-messages="formErrors.assignee_ids"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
