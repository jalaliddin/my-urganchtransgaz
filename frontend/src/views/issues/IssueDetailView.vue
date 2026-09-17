<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import LeafletMap, { type MapMarker } from '@/components/issues/LeafletMap.vue'
import { issueService } from '@/services/issueService'
import { useAuthStore } from '@/stores/auth'
import type { Issue } from '@/types/models'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const issueId = Number(route.params.id)
const issue = ref<Issue | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  issue.value = await issueService.get(issueId)
  loading.value = false
}

onMounted(load)

const canResolve = computed(() => auth.can('issues.resolve') && issue.value?.status === 'open')

const marker = computed<MapMarker[]>(() =>
  issue.value ? [{ id: issue.value.id, lat: issue.value.latitude, lng: issue.value.longitude, title: issue.value.title, status: issue.value.status }] : [],
)

const resolveDialogOpen = ref(false)
const resolutionNote = ref('')
const resolving = ref(false)
async function resolveIssue() {
  if (!issue.value || !resolutionNote.value.trim()) return
  resolving.value = true
  try {
    issue.value = await issueService.resolve(issue.value.id, resolutionNote.value)
    resolveDialogOpen.value = false
    resolutionNote.value = ''
    await load()
  } finally {
    resolving.value = false
  }
}

const newComment = ref('')
const postingComment = ref(false)
async function postComment() {
  if (!issue.value || !newComment.value.trim()) return
  postingComment.value = true
  try {
    await issueService.addComment(issue.value.id, newComment.value)
    newComment.value = ''
    await load()
  } finally {
    postingComment.value = false
  }
}

function goBack() {
  router.push({ name: 'issues' })
}
</script>

<template>
  <AppLoading v-if="loading" />

  <template v-else-if="issue">
    <AppPageHeader :title="issue.title">
      <template #actions>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="goBack">{{ $t('common.close') }}</v-btn>
      </template>
    </AppPageHeader>

    <v-row>
      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-text>
            <div class="d-flex flex-wrap ga-2 mb-3">
              <AppStatusChip :status="issue.status" />
            </div>
            <p class="text-body-1 mb-4">{{ issue.description || '—' }}</p>

            <LeafletMap :markers="marker" :center="[issue.latitude, issue.longitude]" :zoom="14" :height="260" />

            <div v-if="issue.status === 'resolved'" class="mt-4">
              <div class="text-subtitle-2 text-medium-emphasis">{{ $t('issues.resolutionNote') }}</div>
              <p class="text-body-2">{{ issue.resolution_note }}</p>
              <div class="text-caption text-medium-emphasis">
                {{ $t('issues.resolvedBy') }}: {{ issue.resolved_by_name }} · {{ issue.resolved_at }}
              </div>
            </div>

            <div v-if="canResolve" class="mt-4">
              <v-btn color="success" variant="flat" @click="resolveDialogOpen = true">{{ $t('issues.resolve') }}</v-btn>
            </div>
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">{{ $t('issues.comments') }}</v-card-title>
          <v-card-text>
            <div v-for="comment in issue.comments" :key="comment.id" class="mb-3">
              <span class="text-body-2 font-weight-bold">{{ comment.user_name }}</span>
              <p class="text-body-2">{{ comment.body }}</p>
              <v-divider class="mt-2" />
            </div>
            <AppEmptyState v-if="!issue.comments?.length" icon="mdi-comment-outline" />

            <div class="d-flex ga-2 mt-3">
              <v-textarea v-model="newComment" :label="$t('issues.addComment')" rows="2" hide-details />
              <v-btn color="primary" variant="flat" :loading="postingComment" @click="postComment">
                {{ $t('common.save') }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card class="mb-4">
          <v-list density="compact">
            <v-list-item :title="issue.reporter?.full_name ?? '—'" :subtitle="$t('issues.reporter')" prepend-icon="mdi-account-outline" />
            <v-list-item v-if="issue.organization" :title="issue.organization.name" :subtitle="$t('employees.organization')" prepend-icon="mdi-domain" />
            <v-list-item v-if="issue.department" :title="issue.department.name" :subtitle="$t('issues.department')" prepend-icon="mdi-sitemap-outline" />
            <v-list-item v-if="issue.object_name" :title="issue.object_name" :subtitle="$t('issues.objectName')" prepend-icon="mdi-map-marker-outline" />
          </v-list>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">{{ $t('issues.timeline') }}</v-card-title>
          <v-timeline density="compact" side="end" class="pa-4">
            <v-timeline-item v-for="activity in issue.activities" :key="activity.id" size="x-small" dot-color="primary">
              <div class="text-body-2">{{ activity.description }}</div>
              <div class="text-caption text-medium-emphasis">{{ activity.created_at }}</div>
            </v-timeline-item>
          </v-timeline>
          <AppEmptyState v-if="!issue.activities?.length" icon="mdi-history" />
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="resolveDialogOpen" max-width="480">
      <v-card>
        <v-card-title>{{ $t('issues.resolve') }}</v-card-title>
        <v-card-text>
          <v-textarea v-model="resolutionNote" :label="$t('issues.resolutionNote')" rows="3" autofocus />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="resolveDialogOpen = false">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="success" variant="flat" :loading="resolving" :disabled="!resolutionNote.trim()" @click="resolveIssue">
            {{ $t('issues.resolve') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </template>
</template>
