import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { Issue, IssueComment, IssueExecutorCandidate, IssueOptions } from '@/types/models'

export interface IssuePayload {
  title: string
  description?: string | null
  object_name?: string | null
  organization_id?: number | null
  issue_category_id: number
  executor_ids: number[]
  latitude: number
  longitude: number
}

const base = createResourceService<Issue, IssuePayload>('/issues')

export const issueService = {
  ...base,

  async options(): Promise<IssueOptions> {
    const { data } = await http.get<ApiSuccessResponse<IssueOptions>>('/issues/options')
    return data.data
  },

  async executorCandidates(organizationId: number): Promise<IssueExecutorCandidate[]> {
    const { data } = await http.get<ApiSuccessResponse<IssueExecutorCandidate[]>>('/issues/executor-candidates', {
      params: { organization_id: organizationId },
    })
    return data.data
  },

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
