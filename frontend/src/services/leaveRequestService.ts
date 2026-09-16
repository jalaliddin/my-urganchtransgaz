import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { LeaveRequest, LeaveRequestType } from '@/types/models'

export interface LeaveRequestPayload {
  type: LeaveRequestType
  start_date: string
  end_date: string
  reason?: string | null
}

const base = createResourceService<LeaveRequest, LeaveRequestPayload>('/leave-requests')

export const leaveRequestService = {
  ...base,

  async departmentApprove(id: number): Promise<LeaveRequest> {
    const { data } = await http.post<ApiSuccessResponse<LeaveRequest>>(`/leave-requests/${id}/department-approve`)
    return data.data
  },

  async departmentReject(id: number, reason: string): Promise<LeaveRequest> {
    const { data } = await http.post<ApiSuccessResponse<LeaveRequest>>(`/leave-requests/${id}/department-reject`, { reason })
    return data.data
  },

  async approve(id: number): Promise<LeaveRequest> {
    const { data } = await http.post<ApiSuccessResponse<LeaveRequest>>(`/leave-requests/${id}/approve`)
    return data.data
  },

  async reject(id: number, reason: string): Promise<LeaveRequest> {
    const { data } = await http.post<ApiSuccessResponse<LeaveRequest>>(`/leave-requests/${id}/reject`, { reason })
    return data.data
  },

  async cancel(id: number): Promise<LeaveRequest> {
    const { data } = await http.post<ApiSuccessResponse<LeaveRequest>>(`/leave-requests/${id}/cancel`)
    return data.data
  },
}
