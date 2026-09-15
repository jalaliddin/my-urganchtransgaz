<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

import { useNotificationStore } from '@/stores/notifications'

const store = useNotificationStore()
const router = useRouter()

onMounted(() => {
  store.fetchUnreadCount()
})

async function onOpen(open: boolean) {
  if (open) await store.fetchLatest(5)
}

function viewAll() {
  router.push({ name: 'notifications' })
}
</script>

<template>
  <v-menu :close-on-content-click="false" @update:model-value="onOpen">
    <template #activator="{ props: menuProps }">
      <v-btn v-bind="menuProps" icon variant="text">
        <v-badge v-if="store.unreadCount > 0" :content="store.unreadCount" color="error" offset-x="2" offset-y="2">
          <v-icon icon="mdi-bell-outline" />
        </v-badge>
        <v-icon v-else icon="mdi-bell-outline" />
      </v-btn>
    </template>

    <v-card min-width="320" max-width="360">
      <v-list v-if="store.items.length" lines="two" density="comfortable">
        <v-list-item
          v-for="notification in store.items"
          :key="notification.id"
          :active="!notification.read_at"
          :title="notification.title ?? notification.type"
          :subtitle="notification.message ?? ''"
          @click="store.markRead(notification)"
        />
      </v-list>
      <AppEmptyState v-else icon="mdi-bell-off-outline" :message="$t('notifications.noNotifications')" />
      <v-divider />
      <v-card-actions>
        <v-btn block variant="text" @click="viewAll">{{ $t('notifications.viewAll') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-menu>
</template>
