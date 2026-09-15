import { createResourceService } from '@/services/resourceService'
import type { Employee } from '@/types/models'

export interface CreateEmployeePayload {
  organization_id: number | null
  department_id?: number | null
  position_id?: number | null
  employee_number: string
  first_name: string
  last_name: string
  middle_name?: string | null
  phone?: string | null
  corporate_email?: string | null
  create_account?: boolean
  username?: string
  password?: string
  role?: string
}

export interface UpdateEmployeePayload {
  organization_id?: number | null
  department_id?: number | null
  position_id?: number | null
  employee_number?: string
  first_name?: string
  last_name?: string
  middle_name?: string | null
  phone?: string | null
  corporate_email?: string | null
}

export const employeeService = createResourceService<Employee, CreateEmployeePayload, UpdateEmployeePayload>(
  '/employees',
)
