<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import TaskCategoryChip from '@/components/tasks/TaskCategoryChip.vue'
import TaskDueDate from '@/components/tasks/TaskDueDate.vue'
import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { employeeService } from '@/services/employeeService'
import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import type { ListParams, PaginatedResult } from '@/types/api'
import type { Employee, Task, TaskCategory, TaskPriority, TaskStatus, TaskSummary } from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const canCreate = computed(() => auth.can('tasks.create'))

type StatusTab = 'all' | TaskStatus
type Mine = 'all' | 'assigned' | 'created'

const statusTab = ref<StatusTab>('all')
const filters = reactive({
  task_category_id: null as number | null,
  priority: null as TaskPriority | null,
  mine: 'all' as Mine,
})
const sort = ref<string | undefined>(undefined)
const currentSearch = ref('')

// Everything but the status tab — the summary counts one number per tab,
// so it must not itself be narrowed to the selected tab.
function sharedFilterParams(): ListParams {
  return {
    'filter[task_category_id]': filters.task_category_id ?? undefined,
    'filter[priority]': filters.priority ?? undefined,
    'filter[mine]': filters.mine !== 'all' ? filters.mine : undefined,
    'filter[search]': currentSearch.value || undefined,
  }
}

async function fetchTasks(params: ListParams): Promise<PaginatedResult<Task>> {
  currentSearch.value = (params['filter[search]'] as string | undefined) ?? ''
  return taskService.list({
    ...params,
    ...sharedFilterParams(),
    'filter[status]': statusTab.value !== 'all' ? statusTab.value : undefined,
    sort: sort.value,
  })
}

const { items, total, loading, page, itemsPerPage, search, reload } = usePaginatedResource(fetchTasks)

const summary = ref<TaskSummary | null>(null)
async function loadSummary() {
  summary.value = await taskService.summary(sharedFilterParams())
}

function refresh() {
  page.value = 1
  reload()
  loadSummary()
}

watch(filters, refresh)
watch(statusTab, () => {
  page.value = 1
  reload()
})
watch(search, loadSummary)

function onSortBy(value: { key: string; order?: 'asc' | 'desc' }[]) {
  const first = value[0]
  sort.value = first ? `${first.order === 'desc' ? '-' : ''}${first.key}` : undefined
  reload()
}

const statusTabs = computed<{ value: StatusTab; label: string; color?: string }[]>(() => [
  { value: 'all', label: t('tasks.all') },
  { value: 'new', label: t('status.new') },
  { value: 'in_progress', label: t('status.in_progress') },
  { value: 'waiting', label: t('status.waiting'), color: 'warning' },
  { value: 'overdue', label: t('status.overdue'), color: 'error' },
  { value: 'completed', label: t('status.completed') },
  { value: 'cancelled', label: t('status.cancelled') },
])

function tabCount(tab: StatusTab): number | null {
  if (!summary.value) return null
  return tab === 'all' ? summary.value.total : summary.value.by_status[tab]
}

const categories = ref<TaskCategory[]>([])
const employees = ref<Employee[]>([])

onMounted(async () => {
  loadSummary()
  categories.value = (await taskService.options()).categories
  if (canCreate.value) {
    const result = await employeeService.list({ per_page: 200 })
    employees.value = result.data
  }
})

const employeeOptions = computed(() => employees.value.map((e) => ({ title: e.full_name, value: e.id })))
const priorityItems = computed(() => [
  { title: t('status.low'), value: 'low' },
  { title: t('status.normal'), value: 'normal' },
  { title: t('status.high'), value: 'high' },
  { title: t('status.urgent'), value: 'urgent' },
])
const mineItems = computed(() => [
  { title: t('tasks.mineAll'), value: 'all' },
  { title: t('tasks.mineAssigned'), value: 'assigned' },
  { title: t('tasks.mineCreated'), value: 'created' },
])

const headers = computed(() => [
  { title: t('tasks.taskTitle'), key: 'title' },
  { title: t('tasks.category'), key: 'category', sortable: false },
  { title: t('tasks.assignees'), key: 'assignees', sortable: false },
  { title: t('tasks.priority'), key: 'priority', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('tasks.progress'), key: 'progress', sortable: false, width: 130 },
  { title: t('tasks.dueDate'), key: 'due_date' },
])

const dialogOpen = ref(false)
const form = ref({
  title: '',
  description: '',
  task_category_id: null as number | null,
  priority: 'normal' as TaskPriority,
  start_date: '',
  due_date: '',
  assignee_ids: [] as number[],
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  form.value = {
    title: '',
    description: '',
    task_category_id: null,
    priority: 'normal',
    start_date: '',
    due_date: '',
    assignee_ids: [],
  }
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
      task_category_id: form.value.task_category_id,
      priority: form.value.priority,
      start_date: form.value.start_date || null,
      due_date: form.value.due_date || null,
      assignee_ids: form.value.assignee_ids,
    })
    dialogOpen.value = false
    refresh()
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
      <v-btn v-if="auth.can('task_categories.manage')" variant="tonal" prepend-icon="mdi-shape-outline" class="mr-2" to="/task-categories">
        {{ $t('nav.taskCategories') }}
      </v-btn>
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">
        {{ $t('common.create') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <v-card class="mb-4">
    <v-tabs v-model="statusTab" color="primary" show-arrows density="comfortable">
      <v-tab v-for="tab in statusTabs" :key="tab.value" :value="tab.value">
        {{ tab.label }}
        <v-chip
          v-if="tabCount(tab.value) !== null"
          size="x-small"
          class="ml-2"
          variant="tonal"
          :color="tab.color && tabCount(tab.value) ? tab.color : undefined"
        >
          {{ tabCount(tab.value) }}
        </v-chip>
      </v-tab>
    </v-tabs>
    <v-divider />
    <v-card-text>
      <v-row dense>
        <v-col cols="12" sm="4">
          <v-select
            v-model="filters.task_category_id"
            :items="categories"
            item-title="name"
            item-value="id"
            :label="$t('tasks.category')"
            :placeholder="$t('tasks.allCategories')"
            clearable
            hide-details
            density="comfortable"
          >
            <template #item="{ props, item }">
              <v-list-item v-bind="props">
                <template #prepend>
                  <span class="category-dot mr-3" :style="{ background: item.color }" />
                </template>
              </v-list-item>
            </template>
          </v-select>
        </v-col>
        <v-col cols="12" sm="4">
          <v-select
            v-model="filters.priority"
            :items="priorityItems"
            :label="$t('tasks.priority')"
            :placeholder="$t('tasks.allPriorities')"
            clearable
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="4">
          <v-select v-model="filters.mine" :items="mineItems" hide-details density="comfortable" prepend-inner-icon="mdi-account-outline" />
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

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
    @update:sort-by="onSortBy"
  >
    <template #item.title="{ item }">
      <a href="#" class="text-decoration-none font-weight-medium" @click.prevent="openTask(item.id)">{{ item.title }}</a>
    </template>
    <template #item.category="{ item }">
      <TaskCategoryChip :category="item.category" />
    </template>
    <template #item.assignees="{ item }">
      <span v-if="!item.assignees?.length">—</span>
      <template v-else>
        {{ item.assignees[0].full_name }}
        <v-chip v-if="item.assignees.length > 1" size="x-small" class="ml-1" variant="tonal">+{{ item.assignees.length - 1 }}</v-chip>
      </template>
    </template>
    <template #item.priority="{ item }">
      <AppStatusChip :status="item.priority" />
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.progress="{ item }">
      <div class="d-flex align-center ga-2">
        <v-progress-linear
          :model-value="item.progress"
          :color="item.progress === 100 ? 'success' : 'primary'"
          height="6"
          rounded
          :aria-label="$t('tasks.progress')"
        />
        <span class="text-caption">{{ item.progress }}%</span>
      </div>
    </template>
    <template #item.due_date="{ item }">
      <TaskDueDate :due-date="item.due_date" :status="item.status" />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-clipboard-check-outline" :message="$t('tasks.noTasks')" />
    </template>
  </AppDataTable>

  <v-dialog v-model="dialogOpen" max-width="560">
    <v-card>
      <v-card-title>{{ $t('tasks.createTask') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.title" :label="$t('tasks.taskTitle')" :error-messages="formErrors.title" />
        <v-textarea v-model="form.description" :label="$t('tasks.description')" rows="3" :error-messages="formErrors.description" />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.task_category_id"
              :items="categories"
              item-title="name"
              item-value="id"
              :label="$t('tasks.category')"
              clearable
              :error-messages="formErrors.task_category_id"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="form.priority" :items="priorityItems" :label="$t('tasks.priority')" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.start_date" type="date" :label="$t('tasks.startDate')" :error-messages="formErrors.start_date" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.due_date" type="date" :label="$t('tasks.dueDate')" :error-messages="formErrors.due_date" />
          </v-col>
        </v-row>
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

<style scoped>
.category-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
</style>
