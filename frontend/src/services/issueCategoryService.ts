import { createResourceService } from '@/services/resourceService'
import type { IssueCategory } from '@/types/models'

export interface IssueCategoryPayload {
  name: string
  sort_order?: number | null
  status?: 'active' | 'inactive'
}

export const issueCategoryService = createResourceService<IssueCategory, IssueCategoryPayload, Partial<IssueCategoryPayload>>(
  '/issue-categories',
)
