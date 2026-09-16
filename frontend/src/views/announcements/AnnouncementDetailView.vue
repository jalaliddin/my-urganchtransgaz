<script setup lang="ts">
import { computed, onMounted, ref, toRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { announcementService } from '@/services/announcementService'
import { useAuthenticatedImage } from '@/composables/useAuthenticatedImage'
import { useAuthStore } from '@/stores/auth'
import type { Announcement } from '@/types/models'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const announcementId = Number(route.params.id)
const announcement = ref<Announcement | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  announcement.value = await announcementService.get(announcementId)
  loading.value = false
}

onMounted(load)

const imageSource = computed(() => (announcement.value?.has_image ? announcementService.imageUrl(announcement.value.id) : null))
const { objectUrl: imageUrl } = useAuthenticatedImage(toRef(imageSource, 'value'))

const isAuthor = computed(() => announcement.value?.author_id === auth.user?.id)
const canEdit = computed(() => auth.can('announcements.create') && (isAuthor.value || auth.hasRole('central-admin') || auth.hasRole('hr')))
const canPublish = computed(() => auth.can('announcements.publish') && announcement.value?.status === 'draft')
const canArchive = computed(() => auth.can('announcements.publish') && announcement.value?.status !== 'archived')

const acting = ref(false)
async function publish() {
  if (!announcement.value) return
  acting.value = true
  try {
    announcement.value = await announcementService.publish(announcement.value.id)
  } finally {
    acting.value = false
  }
}
async function archive() {
  if (!announcement.value) return
  acting.value = true
  try {
    announcement.value = await announcementService.archive(announcement.value.id)
  } finally {
    acting.value = false
  }
}

async function download() {
  if (!announcement.value) return
  await announcementService.downloadAttachment(announcement.value)
}

function goBack() {
  router.push({ name: 'announcements' })
}
</script>

<template>
  <AppLoading v-if="loading" />

  <template v-else-if="announcement">
    <AppPageHeader :title="announcement.title">
      <template #actions>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="goBack">{{ $t('common.close') }}</v-btn>
      </template>
    </AppPageHeader>

    <v-row>
      <v-col cols="12" md="8">
        <v-card>
          <v-img v-if="imageUrl" :src="imageUrl" height="240" cover />
          <v-card-text>
            <div class="d-flex flex-wrap ga-2 mb-3">
              <AppStatusChip :status="announcement.status" />
              <AppStatusChip :status="announcement.priority" />
            </div>
            <p class="text-body-1" style="white-space: pre-wrap">{{ announcement.content }}</p>

            <div v-if="announcement.has_attachment" class="mt-4">
              <v-btn variant="tonal" prepend-icon="mdi-paperclip" @click="download">
                {{ announcement.attachment_name ?? $t('announcements.attachment') }}
              </v-btn>
            </div>

            <div class="d-flex flex-wrap ga-2 mt-4">
              <v-btn v-if="canPublish" color="success" variant="flat" size="small" :loading="acting" @click="publish">
                {{ $t('announcements.publish') }}
              </v-btn>
              <v-btn v-if="canArchive" color="error" variant="outlined" size="small" :loading="acting" @click="archive">
                {{ $t('announcements.archive') }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card>
          <v-list density="compact">
            <v-list-item :title="announcement.author_name ?? '—'" :subtitle="$t('announcements.author')" prepend-icon="mdi-account-outline" />
            <v-list-item v-if="announcement.publish_at" :title="announcement.publish_at" :subtitle="$t('announcements.publishAt')" prepend-icon="mdi-calendar-arrow-right" />
            <v-list-item v-if="announcement.expire_at" :title="announcement.expire_at" :subtitle="$t('announcements.expireAt')" prepend-icon="mdi-calendar-arrow-left" />
            <v-list-item v-if="canEdit && announcement.reads_count !== undefined" :title="String(announcement.reads_count)" :subtitle="$t('announcements.reads')" prepend-icon="mdi-eye-outline" />
          </v-list>
          <v-divider />
          <v-list density="compact" :title="$t('announcements.target')">
            <v-list-item v-for="target in announcement.targets" :key="target.id" :title="target.label" prepend-icon="mdi-bullseye-arrow" />
          </v-list>
        </v-card>
      </v-col>
    </v-row>
  </template>
</template>
