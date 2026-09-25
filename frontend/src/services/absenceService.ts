import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams, PaginatedResult } from '@/types/api'
import type { AbsenceType, EmployeeAbsence, LeaveBalance } from '@/types/models'

export interface AbsencePayload {
  employee_id?: number
  type: AbsenceType
  start_date: string
  end_date: string
  document_number?: string | null
  document_date?: string | null
  destination?: string | null
  notes?: string | null
  file?: File | null
}

function toFormData(payload: AbsencePayload): FormData {
  const form = new FormData()
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') form.append(key, value as string | Blob)
  })
  return form
}

function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export const absenceService = {
  async list(params: ListParams = {}): Promise<PaginatedResult<EmployeeAbsence>> {
    const { data } = await http.get<ApiSuccessResponse<EmployeeAbsence[]>>('/absences', { params })
    return { data: data.data, meta: data.meta! }
  },

  async create(payload: AbsencePayload): Promise<EmployeeAbsence> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeAbsence>>('/absences', toFormData(payload))
    return data.data
  },

  /**
   * PHP only parses multipart bodies on POST, so an update that may carry
   * a file goes as POST with Laravel's _method override.
   */
  async update(id: number, payload: AbsencePayload): Promise<EmployeeAbsence> {
    const form = toFormData(payload)
    form.append('_method', 'PUT')
    const { data } = await http.post<ApiSuccessResponse<EmployeeAbsence>>(`/absences/${id}`, form)
    return data.data
  },

  async cancel(id: number, reason: string): Promise<EmployeeAbsence> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeAbsence>>(`/absences/${id}/cancel`, { reason })
    return data.data
  },

  /** Behind an authenticated endpoint — see documentService.download. */
  async download(absence: EmployeeAbsence): Promise<void> {
    const response = await http.get(`/absences/${absence.id}/download`, { responseType: 'blob' })
    saveBlob(response.data as Blob, absence.file_name ?? `absence-${absence.id}`)
  },

  async export(format: 'csv' | 'xlsx' | 'pdf', params: ListParams = {}): Promise<void> {
    const response = await http.get('/absences', { params: { ...params, export: format }, responseType: 'blob' })
    saveBlob(response.data as Blob, `absences.${format}`)
  },

  async balance(employeeId: number, year?: number): Promise<LeaveBalance> {
    const { data } = await http.get<ApiSuccessResponse<LeaveBalance>>(`/employees/${employeeId}/leave-balance`, {
      params: year ? { year } : {},
    })
    return data.data
  },
}
