<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import type { Task } from '@/types/models'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const taskId = Number(route.params.id)
const task = ref<Task | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  task.value = await taskService.get(taskId)
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
</script>

<template>
  <AppLoading v-if="loading" />

  <template v-else-if="task">
    <AppPageHeader :title="task.title">
      <template #actions>
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
            <v-list-item :title="task.due_date ?? '—'" :subtitle="$t('tasks.dueDate')" prepend-icon="mdi-calendar-outline" />
          </v-list>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">{{ $t('tasks.activity') }}</v-card-title>
          <v-timeline density="compact" side="end" class="pa-4">
            <v-timeline-item v-for="activity in task.activities" :key="activity.id" size="x-small" dot-color="primary">
              <div class="text-body-2">{{ activity.description }}</div>
              <div class="text-caption text-medium-emphasis">{{ activity.created_at }}</div>
            </v-timeline-item>
          </v-timeline>
          <AppEmptyState v-if="!task.activities?.length" icon="mdi-history" />
        </v-card>
      </v-col>
    </v-row>
  </template>
</template>
