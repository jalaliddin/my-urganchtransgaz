import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { AttendanceRecord, AttendanceReportRow, AttendanceStatus, TodayAttendance } from '@/types/models'

export interface AttendanceEntryPayload {
  employee_id: number
  date: string
  check_in?: string | null
  check_out?: string | null
  status?: AttendanceStatus
  notes?: string | null
}

export interface AttendanceReportParams {
  group_by: 'employee' | 'department' | 'organization'
  from?: string
  to?: string
  organization_id?: number
  department_id?: number
  employee_id?: number
}

const base = createResourceService<AttendanceRecord, AttendanceEntryPayload, Partial<AttendanceEntryPayload>>(
  '/attendance',
)

export const attendanceService = {
  ...base,

  async today(): Promise<TodayAttendance[]> {
    const { data } = await http.get<ApiSuccessResponse<TodayAttendance[]>>('/attendance/today')
    return data.data
  },

  async checkIn(): Promise<AttendanceRecord> {
    const { data } = await http.post<ApiSuccessResponse<AttendanceRecord>>('/attendance/check-in')
    return data.data
  },

  async checkOut(): Promise<AttendanceRecord> {
    const { data } = await http.post<ApiSuccessResponse<AttendanceRecord>>('/attendance/check-out')
    return data.data
  },

  async report(params: AttendanceReportParams): Promise<AttendanceReportRow[]> {
    const { data } = await http.get<ApiSuccessResponse<AttendanceReportRow[]>>('/attendance/report', { params })
    return data.data
  },
}
