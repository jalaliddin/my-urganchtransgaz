import { createResourceService } from '@/services/resourceService'
import type { TaskCategory } from '@/types/models'

export interface TaskCategoryPayload {
  name: string
  color?: string
  sort_order?: number | null
  status?: 'active' | 'inactive'
}

export const taskCategoryService = createResourceService<TaskCategory, TaskCategoryPayload, Partial<TaskCategoryPayload>>(
  '/task-categories',
)
