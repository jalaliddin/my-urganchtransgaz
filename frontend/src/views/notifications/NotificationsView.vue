<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { notificationService } from '@/services/notificationService'
import { useNotificationStore } from '@/stores/notifications'
import type { AppNotification } from '@/types/models'

const notificationStore = useNotificationStore()
const notifications = ref<AppNotification[]>([])
const loading = ref(true)
const unreadOnly = ref(false)

async function load() {
  loading.value = true
  const result = await notificationService.list({ per_page: 30, unread_only: unreadOnly.value ? 1 : undefined })
  notifications.value = result.data
  loading.value = false
}

onMounted(load)

async function markRead(notification: AppNotification) {
  await notificationStore.markRead(notification)
}

async function markAllRead() {
  await notificationStore.markAllRead()
  await load()
}
</script>

<template>
  <AppPageHeader :title="$t('notifications.title')">
    <template #actions>
      <v-switch v-model="unreadOnly" :label="$t('notifications.title')" hide-details class="mr-2" @update:model-value="load" />
      <v-btn variant="tonal" prepend-icon="mdi-check-all" @click="markAllRead">
        {{ $t('notifications.markAllRead') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <AppLoading v-if="loading" />

  <v-card v-else>
    <v-list v-if="notifications.length" lines="two">
      <template v-for="(notification, index) in notifications" :key="notification.id">
        <v-list-item
          :active="!notification.read_at"
          :title="notification.title ?? notification.type"
          :subtitle="notification.message ?? ''"
          @click="markRead(notification)"
        >
          <template #prepend>
            <v-icon :color="notification.read_at ? 'grey' : 'primary'" icon="mdi-bell-outline" />
          </template>
          <template #append>
            <span class="text-caption text-medium-emphasis">{{ new Date(notification.created_at).toLocaleString() }}</span>
          </template>
        </v-list-item>
        <v-divider v-if="index < notifications.length - 1" />
      </template>
    </v-list>
    <AppEmptyState v-else icon="mdi-bell-off-outline" :message="$t('notifications.noNotifications')" />
  </v-card>
</template>
