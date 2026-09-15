import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams, PaginatedResult } from '@/types/api'
import type { DocumentType, EmployeeDocument } from '@/types/models'

export interface UploadDocumentPayload {
  employee_id?: number
  document_type_id: number
  title: string
  document_number?: string
  issue_date?: string
  expiry_date?: string
  file: File
}

export const documentService = {
  async list(params: ListParams = {}): Promise<PaginatedResult<EmployeeDocument>> {
    const { data } = await http.get<ApiSuccessResponse<EmployeeDocument[]>>('/documents', { params })
    return { data: data.data, meta: data.meta! }
  },

  async get(id: number): Promise<EmployeeDocument> {
    const { data } = await http.get<ApiSuccessResponse<EmployeeDocument>>(`/documents/${id}`)
    return data.data
  },

  async upload(payload: UploadDocumentPayload): Promise<EmployeeDocument> {
    const form = new FormData()
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== undefined && value !== null) form.append(key, value as string | Blob)
    })
    const { data } = await http.post<ApiSuccessResponse<EmployeeDocument>>('/documents', form)
    return data.data
  },

  async approve(id: number): Promise<EmployeeDocument> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeDocument>>(`/documents/${id}/approve`)
    return data.data
  },

  async reject(id: number, reason: string): Promise<EmployeeDocument> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeDocument>>(`/documents/${id}/reject`, { reason })
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/documents/${id}`)
  },

  /**
   * Documents live on a private disk behind an authenticated endpoint, so a
   * plain <a href> can't fetch one — the browser won't attach the Bearer
   * token. Fetch it as a blob instead and trigger the save from that.
   */
  async download(document: EmployeeDocument, filename: string): Promise<void> {
    const response = await http.get(`/documents/${document.id}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data as Blob)
    const link = window.document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  },
}

export const documentTypeService = {
  async list(): Promise<DocumentType[]> {
    const { data } = await http.get<ApiSuccessResponse<DocumentType[]>>('/document-types')
    return data.data
  },
}
