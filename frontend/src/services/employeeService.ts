import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams } from '@/types/api'
import type { Employee, ImportResult } from '@/types/models'

export interface CreateEmployeePayload {
  organization_id: number | null
  department_id?: number | null
  position_id?: number | null
  employee_number: string
  dahua_person_id?: string | null
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
  dahua_person_id?: string | null
  first_name?: string
  last_name?: string
  middle_name?: string | null
  phone?: string | null
  corporate_email?: string | null
}

export interface CreateEmployeeAccountPayload {
  username: string
  corporate_email?: string | null
  password: string
  role: string
}

export interface ResetPasswordPayload {
  password: string
  password_confirmation: string
}

const base = createResourceService<Employee, CreateEmployeePayload, UpdateEmployeePayload>('/employees')

export const employeeService = {
  ...base,

  async updateRole(id: number, role: string): Promise<void> {
    await http.put(`/employees/${id}/role`, { role })
  },

  /** Opens a login for an employee who was added without one. */
  async createAccount(id: number, payload: CreateEmployeeAccountPayload): Promise<Employee> {
    const { data } = await http.post<ApiSuccessResponse<Employee>>(`/employees/${id}/account`, payload)
    return data.data
  },

  /** Sets a new password for an employee's existing account (an admin reset, not self-service). */
  async resetPassword(id: number, payload: ResetPasswordPayload): Promise<void> {
    await http.put(`/employees/${id}/password`, payload)
  },

  /**
   * The list lives on a private disk behind an authenticated endpoint,
   * so a plain <a href> can't fetch it — same reasoning as
   * documentService.download.
   */
  async export(format: 'csv' | 'xlsx' | 'pdf', params: ListParams = {}): Promise<void> {
    const response = await http.get('/employees', { params: { ...params, export: format }, responseType: 'blob' })
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `employees.${format}`
    link.click()
    URL.revokeObjectURL(url)
  },
}

export const employeeImportService = {
  async downloadTemplate(): Promise<void> {
    const response = await http.get('/employees/import/template', { responseType: 'blob' })
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'employees-import-template.csv'
    link.click()
    URL.revokeObjectURL(url)
  },

  async upload(file: File, dryRun: boolean): Promise<ImportResult> {
    const form = new FormData()
    form.append('file', file)
    form.append('dry_run', dryRun ? '1' : '0')
    const { data } = await http.post<ApiSuccessResponse<ImportResult>>(
      `/employees/import?dry_run=${dryRun ? 1 : 0}`,
      form,
    )
    return data.data
  },
}
