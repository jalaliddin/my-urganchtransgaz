<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import TaskCategoryChip from '@/components/tasks/TaskCategoryChip.vue'
import TaskDueDate from '@/components/tasks/TaskDueDate.vue'
import { employeeService } from '@/services/employeeService'
import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import type { Employee, Task, TaskCategory, TaskPriority } from '@/types/models'
import { formatDate, formatDateTime } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const taskId = Number(route.params.id)
const task = ref<Task | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  task.value = await taskService.get(taskId)
  // The slider starts from the task's real progress, not 0 — otherwise
  // pressing "update" without touching it would reset the progress.
  progressValue.value = task.value.progress
  loading.value = false
}

onMounted(load)

const isAssignee = computed(
  () => !!task.value?.assignees?.some((a) => a.id === auth.user?.employee?.id),
)
const isManager = computed(
  () => auth.can('tasks.assign') || auth.user?.id === task.value?.creator_id,
)
const canUpdateProgress = computed(
  () => isAssignee.value && task.value && !['completed', 'cancelled'].includes(task.value.status),
)
const canComplete = computed(
  () => isAssignee.value && task.value && ['new', 'in_progress', 'overdue'].includes(task.value.status),
)
const canApprove = computed(() => isManager.value && task.value?.status === 'waiting')
const canReopen = computed(() => isManager.value && ['waiting', 'completed'].includes(task.value?.status ?? ''))
const canCancel = computed(
  () => isManager.value && task.value && !['completed', 'cancelled'].includes(task.value.status),
)

const progressValue = ref(0)
const savingProgress = ref(false)
async function saveProgress() {
  if (!task.value) return
  savingProgress.value = true
  try {
    await taskService.updateProgress(task.value.id, progressValue.value)
    await load()
  } finally {
    savingProgress.value = false
  }
}

const completing = ref(false)
const completeResult = ref('')
async function markComplete() {
  if (!task.value) return
  completing.value = true
  try {
    task.value = await taskService.complete(task.value.id, completeResult.value || undefined)
    await load()
  } finally {
    completing.value = false
  }
}

const acting = ref(false)
async function approve() {
  if (!task.value) return
  acting.value = true
  try {
    task.value = await taskService.approve(task.value.id)
    await load()
  } finally {
    acting.value = false
  }
}
async function reopen() {
  if (!task.value) return
  acting.value = true
  try {
    task.value = await taskService.reopen(task.value.id)
    await load()
  } finally {
    acting.value = false
  }
}
async function cancelTask() {
  if (!task.value) return
  acting.value = true
  try {
    task.value = await taskService.cancel(task.value.id)
    await load()
  } finally {
    acting.value = false
  }
}

const newComment = ref('')
const postingComment = ref(false)
async function postComment() {
  if (!task.value || !newComment.value.trim()) return
  postingComment.value = true
  try {
    await taskService.addComment(task.value.id, newComment.value)
    newComment.value = ''
    await load()
  } finally {
    postingComment.value = false
  }
}

async function removeComment(commentId: number) {
  if (!task.value) return
  await taskService.deleteComment(task.value.id, commentId)
  await load()
}

const uploadingFile = ref<File | null>(null)
const uploading = ref(false)
async function uploadFile() {
  if (!task.value || !uploadingFile.value) return
  uploading.value = true
  try {
    await taskService.uploadAttachment(task.value.id, uploadingFile.value)
    uploadingFile.value = null
    await load()
  } finally {
    uploading.value = false
  }
}

async function downloadFile(attachmentId: number, filename: string) {
  if (!task.value) return
  await taskService.downloadAttachment(task.value.id, attachmentId, filename)
}

async function removeAttachment(attachmentId: number) {
  if (!task.value) return
  await taskService.deleteAttachment(task.value.id, attachmentId)
  await load()
}

function goBack() {
  router.push({ name: 'tasks' })
}

const canEdit = computed(() => isManager.value && task.value?.status !== 'cancelled')
const editOpen = ref(false)
const editSaving = ref(false)
const editErrors = ref<Record<string, string[]>>({})
const editForm = ref({
  title: '',
  description: '',
  task_category_id: null as number | null,
  priority: 'normal' as TaskPriority,
  start_date: '',
  due_date: '',
  assignee_ids: [] as number[],
})
const categories = ref<TaskCategory[]>([])
const employees = ref<Employee[]>([])

const priorityItems = computed(() => [
  { title: t('status.low'), value: 'low' },
  { title: t('status.normal'), value: 'normal' },
  { title: t('status.high'), value: 'high' },
  { title: t('status.urgent'), value: 'urgent' },
])

// A category deactivated since the task was filed stays selectable for this
// task (the server accepts it), so it's added back to the active list here.
const categoryItems = computed(() => {
  const current = task.value?.category
  return current && !categories.value.some((category) => category.id === current.id)
    ? [...categories.value, current]
    : categories.value
})

// Current assignees are kept in the options even if the 200-employee page
// doesn't include them, so the autocomplete never shows a bare id.
const employeeItems = computed(() => {
  const byId = new Map<number, { title: string; value: number }>()
  for (const employee of [...employees.value, ...(task.value?.assignees ?? [])]) {
    byId.set(employee.id, { title: employee.full_name, value: employee.id })
  }
  return [...byId.values()]
})

async function openEdit() {
  if (!task.value) return
  editForm.value = {
    title: task.value.title,
    description: task.value.description ?? '',
    task_category_id: task.value.task_category_id,
    priority: task.value.priority,
    start_date: task.value.start_date ?? '',
    due_date: task.value.due_date ?? '',
    assignee_ids: (task.value.assignees ?? []).map((assignee) => assignee.id),
  }
  editErrors.value = {}
  editOpen.value = true

  const [options, employeePage] = await Promise.all([taskService.options(), employeeService.list({ per_page: 200 })])
  categories.value = options.categories
  employees.value = employeePage.data
}

async function saveEdit() {
  if (!task.value) return
  editSaving.value = true
  editErrors.value = {}
  try {
    await taskService.update(task.value.id, {
      title: editForm.value.title,
      description: editForm.value.description || null,
      task_category_id: editForm.value.task_category_id,
      priority: editForm.value.priority,
      start_date: editForm.value.start_date || null,
      due_date: editForm.value.due_date || null,
      assignee_ids: editForm.value.assignee_ids,
    })
    editOpen.value = false
    await load()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    editErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    editSaving.value = false
  }
}
</script>

<template>
  <AppLoading v-if="loading" />

  <template v-else-if="task">
    <AppPageHeader :title="task.title">
      <template #actions>
        <v-btn v-if="canEdit" variant="tonal" prepend-icon="mdi-pencil-outline" class="mr-2" @click="openEdit">
          {{ $t('common.edit') }}
        </v-btn>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="goBack">{{ $t('common.close') }}</v-btn>
      </template>
    </AppPageHeader>

    <v-row>
      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-text>
            <div class="d-flex flex-wrap ga-2 mb-3">
              <AppStatusChip :status="task.status" />
              <AppStatusChip :status="task.priority" />
              <TaskCategoryChip v-if="task.category" :category="task.category" />
            </div>
            <p class="text-body-1 mb-4">{{ task.description || '—' }}</p>

            <div class="mb-2 d-flex justify-space-between">
              <span class="text-body-2">{{ $t('tasks.progress') }}</span>
              <span class="text-body-2 font-weight-bold">{{ task.progress }}%</span>
            </div>
            <v-progress-linear :model-value="task.progress" color="primary" height="10" rounded class="mb-4" />

            <div v-if="canUpdateProgress" class="d-flex align-center ga-3 mb-2">
              <v-slider v-model="progressValue" :min="0" :max="100" :step="5" thumb-label hide-details style="max-width: 300px" />
              <v-btn size="small" variant="tonal" :loading="savingProgress" @click="saveProgress">
                {{ $t('tasks.updateProgress') }}
              </v-btn>
            </div>

            <div v-if="canComplete" class="d-flex flex-wrap align-center ga-2 mt-2">
              <v-text-field v-model="completeResult" :label="$t('tasks.result')" density="compact" hide-details style="max-width: 320px" />
              <v-btn color="success" variant="flat" size="small" :loading="completing" @click="markComplete">
                {{ $t('tasks.markComplete') }}
              </v-btn>
            </div>

            <div class="d-flex flex-wrap ga-2 mt-4">
              <v-btn v-if="canApprove" color="success" variant="flat" size="small" :loading="acting" @click="approve">
                {{ $t('tasks.approve') }}
              </v-btn>
              <v-btn v-if="canReopen" color="secondary" variant="flat" size="small" :loading="acting" @click="reopen">
                {{ $t('tasks.reopen') }}
              </v-btn>
              <v-btn v-if="canCancel" color="error" variant="outlined" size="small" :loading="acting" @click="cancelTask">
                {{ $t('tasks.cancel') }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">{{ $t('tasks.comments') }}</v-card-title>
          <v-card-text>
            <div v-for="comment in task.comments" :key="comment.id" class="mb-3">
              <div class="d-flex justify-space-between">
                <span class="text-body-2 font-weight-bold">{{ comment.user_name }}</span>
                <v-btn
                  v-if="comment.user_id === auth.user?.id || isManager"
                  icon="mdi-close"
                  size="x-small"
                  variant="text"
                  @click="removeComment(comment.id)"
                />
              </div>
              <p class="text-body-2">{{ comment.body }}</p>
              <v-divider class="mt-2" />
            </div>
            <AppEmptyState v-if="!task.comments?.length" icon="mdi-comment-outline" />

            <div class="d-flex ga-2 mt-3">
              <v-textarea v-model="newComment" :label="$t('tasks.addComment')" rows="2" hide-details />
              <v-btn color="primary" variant="flat" :loading="postingComment" @click="postComment">
                {{ $t('common.save') }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">{{ $t('tasks.attachments') }}</v-card-title>
          <v-card-text>
            <v-list density="compact">
              <v-list-item v-for="attachment in task.attachments" :key="attachment.id" :title="attachment.original_name">
                <template #append>
                  <v-btn icon="mdi-download-outline" variant="text" size="small" @click="downloadFile(attachment.id, attachment.original_name)" />
                  <v-btn
                    v-if="attachment.uploaded_by === auth.user?.id || isManager"
                    icon="mdi-delete-outline"
                    variant="text"
                    size="small"
                    @click="removeAttachment(attachment.id)"
                  />
                </template>
              </v-list-item>
            </v-list>
            <AppEmptyState v-if="!task.attachments?.length" icon="mdi-paperclip" />

            <div class="d-flex align-center ga-2 mt-3">
              <AppFileUpload v-model="uploadingFile" accept="application/pdf,image/jpeg,image/png" />
              <v-btn color="primary" variant="tonal" :disabled="!uploadingFile" :loading="uploading" @click="uploadFile">
                {{ $t('common.save') }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card class="mb-4">
          <v-list density="compact">
            <v-list-item :title="task.creator_name ?? '—'" :subtitle="$t('tasks.creator')" prepend-icon="mdi-account-outline" />
            <v-list-item
              :title="(task.assignees ?? []).map((a) => a.full_name).join(', ') || '—'"
              :subtitle="$t('tasks.assignees')"
              prepend-icon="mdi-account-group-outline"
            />
            <v-list-item v-if="task.organization" :title="task.organization.name" :subtitle="$t('employees.organization')" prepend-icon="mdi-domain" />
            <v-list-item v-if="task.department" :title="task.department.name" :subtitle="$t('employees.department')" prepend-icon="mdi-sitemap-outline" />
            <v-list-item v-if="task.start_date" :title="formatDate(task.start_date)" :subtitle="$t('tasks.startDate')" prepend-icon="mdi-calendar-start-outline" />
            <v-list-item prepend-icon="mdi-calendar-outline" :subtitle="$t('tasks.dueDate')">
              <TaskDueDate :due-date="task.due_date" :status="task.status" />
            </v-list-item>
          </v-list>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">{{ $t('tasks.activity') }}</v-card-title>
          <v-timeline density="compact" side="end" class="pa-4">
            <v-timeline-item v-for="activity in task.activities" :key="activity.id" size="x-small" dot-color="primary">
              <div class="text-body-2">{{ activity.description }}</div>
              <div class="text-caption text-medium-emphasis">{{ formatDateTime(activity.created_at) }}</div>
            </v-timeline-item>
          </v-timeline>
          <AppEmptyState v-if="!task.activities?.length" icon="mdi-history" />
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="editOpen" max-width="560">
      <v-card>
        <v-card-title>{{ $t('tasks.editTask') }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="editForm.title" :label="$t('tasks.taskTitle')" :error-messages="editErrors.title" />
          <v-textarea v-model="editForm.description" :label="$t('tasks.description')" rows="3" :error-messages="editErrors.description" />
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-select
                v-model="editForm.task_category_id"
                :items="categoryItems"
                item-title="name"
                item-value="id"
                :label="$t('tasks.category')"
                clearable
                :error-messages="editErrors.task_category_id"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select v-model="editForm.priority" :items="priorityItems" :label="$t('tasks.priority')" :error-messages="editErrors.priority" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="editForm.start_date" type="date" :label="$t('tasks.startDate')" :error-messages="editErrors.start_date" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="editForm.due_date" type="date" :label="$t('tasks.dueDate')" :error-messages="editErrors.due_date" />
            </v-col>
          </v-row>
          <v-autocomplete
            v-model="editForm.assignee_ids"
            :items="employeeItems"
            :label="$t('tasks.assignees')"
            multiple
            chips
            closable-chips
            :error-messages="editErrors.assignee_ids"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editOpen = false">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="primary" variant="flat" :loading="editSaving" @click="saveEdit">{{ $t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </template>
</template>
