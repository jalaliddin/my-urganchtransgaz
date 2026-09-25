import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams } from '@/types/api'
import type { Task, TaskCategory, TaskComment, TaskPriority, TaskSummary } from '@/types/models'

export interface TaskPayload {
  title: string
  description?: string | null
  organization_id?: number | null
  department_id?: number | null
  task_category_id?: number | null
  priority?: TaskPriority
  start_date?: string | null
  due_date?: string | null
  assignee_ids: number[]
}

const base = createResourceService<Task, TaskPayload, Partial<TaskPayload>>('/tasks')

export const taskService = {
  ...base,

  async summary(params: ListParams = {}): Promise<TaskSummary> {
    const { data } = await http.get<ApiSuccessResponse<TaskSummary>>('/tasks/summary', { params })
    return data.data
  },

  async options(): Promise<{ categories: TaskCategory[] }> {
    const { data } = await http.get<ApiSuccessResponse<{ categories: TaskCategory[] }>>('/tasks/options')
    return data.data
  },

  async updateProgress(id: number, progress: number): Promise<Task> {
    const { data } = await http.patch<ApiSuccessResponse<Task>>(`/tasks/${id}/progress`, { progress })
    return data.data
  },

  async complete(id: number, result?: string): Promise<Task> {
    const { data } = await http.post<ApiSuccessResponse<Task>>(`/tasks/${id}/complete`, { result })
    return data.data
  },

  async approve(id: number): Promise<Task> {
    const { data } = await http.post<ApiSuccessResponse<Task>>(`/tasks/${id}/approve`)
    return data.data
  },

  async reopen(id: number): Promise<Task> {
    const { data } = await http.post<ApiSuccessResponse<Task>>(`/tasks/${id}/reopen`)
    return data.data
  },

  async cancel(id: number): Promise<Task> {
    const { data } = await http.post<ApiSuccessResponse<Task>>(`/tasks/${id}/cancel`)
    return data.data
  },

  async addComment(taskId: number, body: string): Promise<TaskComment> {
    const { data } = await http.post<ApiSuccessResponse<TaskComment>>(`/tasks/${taskId}/comments`, { body })
    return data.data
  },

  async deleteComment(taskId: number, commentId: number): Promise<void> {
    await http.delete(`/tasks/${taskId}/comments/${commentId}`)
  },

  async uploadAttachment(taskId: number, file: File) {
    const form = new FormData()
    form.append('file', file)
    const { data } = await http.post(`/tasks/${taskId}/attachments`, form)
    return data.data
  },

  async downloadAttachment(taskId: number, attachmentId: number, filename: string): Promise<void> {
    const response = await http.get(`/tasks/${taskId}/attachments/${attachmentId}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data as Blob)
    const link = window.document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  },

  async deleteAttachment(taskId: number, attachmentId: number): Promise<void> {
    await http.delete(`/tasks/${taskId}/attachments/${attachmentId}`)
  },
}
