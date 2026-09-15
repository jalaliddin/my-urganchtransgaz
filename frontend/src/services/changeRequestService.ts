import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams, PaginatedResult } from '@/types/api'
import type { EmployeeChangeRequest } from '@/types/models'

export const changeRequestService = {
  async list(params: ListParams = {}): Promise<PaginatedResult<EmployeeChangeRequest>> {
    const { data } = await http.get<ApiSuccessResponse<EmployeeChangeRequest[]>>('/change-requests', { params })
    return { data: data.data, meta: data.meta! }
  },

  async approve(id: number): Promise<EmployeeChangeRequest> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeChangeRequest>>(`/change-requests/${id}/approve`)
    return data.data
  },

  async reject(id: number, reason: string): Promise<EmployeeChangeRequest> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeChangeRequest>>(`/change-requests/${id}/reject`, { reason })
    return data.data
  },
}
