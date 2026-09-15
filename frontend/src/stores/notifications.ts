import { ref } from 'vue'
import { defineStore } from 'pinia'

import { notificationService } from '@/services/notificationService'
import type { AppNotification } from '@/types/models'

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)

  async function fetchUnreadCount(): Promise<void> {
    unreadCount.value = await notificationService.unreadCount()
  }

  async function fetchLatest(limit = 5): Promise<void> {
    loading.value = true
    try {
      const result = await notificationService.list({ per_page: limit })
      items.value = result.data
    } finally {
      loading.value = false
    }
  }

  async function markRead(notification: AppNotification): Promise<void> {
    if (notification.read_at) return
    await notificationService.markRead(notification.id)
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }

  async function markAllRead(): Promise<void> {
    await notificationService.markAllRead()
    items.value.forEach((n) => {
      n.read_at = n.read_at ?? new Date().toISOString()
    })
    unreadCount.value = 0
  }

  return { items, unreadCount, loading, fetchUnreadCount, fetchLatest, markRead, markAllRead }
})
