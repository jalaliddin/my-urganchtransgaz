import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { Issue, IssueComment } from '@/types/models'

export interface IssuePayload {
  title: string
  description?: string | null
  object_name?: string | null
  organization_id?: number | null
  department_id?: number | null
  latitude: number
  longitude: number
}

const base = createResourceService<Issue, IssuePayload>('/issues')

export const issueService = {
  ...base,

  async resolve(id: number, resolutionNote: string): Promise<Issue> {
    const { data } = await http.post<ApiSuccessResponse<Issue>>(`/issues/${id}/resolve`, {
      resolution_note: resolutionNote,
    })
    return data.data
  },

  async addComment(issueId: number, body: string): Promise<IssueComment> {
    const { data } = await http.post<ApiSuccessResponse<IssueComment>>(`/issues/${issueId}/comments`, { body })
    return data.data
  },
}
