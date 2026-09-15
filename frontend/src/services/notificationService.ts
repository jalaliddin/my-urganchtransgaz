import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams, PaginatedResult } from '@/types/api'
import type { AppNotification } from '@/types/models'

export const notificationService = {
  async list(params: ListParams = {}): Promise<PaginatedResult<AppNotification>> {
    const { data } = await http.get<ApiSuccessResponse<AppNotification[]>>('/notifications', { params })
    return { data: data.data, meta: data.meta! }
  },

  async unreadCount(): Promise<number> {
    const { data } = await http.get<ApiSuccessResponse<{ count: number }>>('/notifications/unread-count')
    return data.data.count
  },

  async markRead(id: string): Promise<AppNotification> {
    const { data } = await http.post<ApiSuccessResponse<AppNotification>>(`/notifications/${id}/read`)
    return data.data
  },

  async markAllRead(): Promise<void> {
    await http.post('/notifications/read-all')
  },
}
