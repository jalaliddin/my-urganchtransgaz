import { createResourceService } from '@/services/resourceService'
import type { Department } from '@/types/models'

export interface DepartmentPayload {
  organization_id: number | null
  manager_id?: number | null
  name: string
  code: string
  description?: string | null
}

export const departmentService = createResourceService<Department, DepartmentPayload, Partial<DepartmentPayload>>(
  '/departments',
)
