import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { Announcement, AnnouncementTargetType, Role, TaskPriority } from '@/types/models'

export interface AnnouncementTargetPayload {
  target_type: AnnouncementTargetType
  target_id?: number | null
}

export interface AnnouncementPayload {
  title: string
  content: string
  priority?: TaskPriority
  publish_at?: string | null
  expire_at?: string | null
  publish_immediately?: boolean
  targets: AnnouncementTargetPayload[]
}

const base = createResourceService<Announcement, AnnouncementPayload, AnnouncementPayload>('/announcements')

export const announcementService = {
  ...base,

  async publish(id: number): Promise<Announcement> {
    const { data } = await http.post<ApiSuccessResponse<Announcement>>(`/announcements/${id}/publish`)
    return data.data
  },

  async archive(id: number): Promise<Announcement> {
    const { data } = await http.post<ApiSuccessResponse<Announcement>>(`/announcements/${id}/archive`)
    return data.data
  },

  async uploadImage(id: number, file: File): Promise<Announcement> {
    const form = new FormData()
    form.append('image', file)
    const { data } = await http.post<ApiSuccessResponse<Announcement>>(`/announcements/${id}/image`, form)
    return data.data
  },

  async uploadAttachment(id: number, file: File): Promise<Announcement> {
    const form = new FormData()
    form.append('attachment', file)
    const { data } = await http.post<ApiSuccessResponse<Announcement>>(`/announcements/${id}/attachment`, form)
    return data.data
  },

  imageUrl(id: number): string {
    return `/announcements/${id}/image`
  },

  /**
   * The attachment lives on a private disk behind an authenticated
   * endpoint, so a plain <a href> can't fetch it — same reasoning as
   * documentService.download.
   */
  async downloadAttachment(announcement: Announcement): Promise<void> {
    const response = await http.get(`/announcements/${announcement.id}/attachment`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data as Blob)
    const link = window.document.createElement('a')
    link.href = url
    link.download = announcement.attachment_name ?? 'attachment'
    link.click()
    URL.revokeObjectURL(url)
  },
}

export const roleService = {
  async list(): Promise<Role[]> {
    const { data } = await http.get<ApiSuccessResponse<Role[]>>('/roles')
    return data.data
  },
}
