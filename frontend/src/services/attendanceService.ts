import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { AttendanceEvent, AttendanceRecord, AttendanceReportRow, AttendanceStatus, Timesheet, TodayAttendance } from '@/types/models'

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

export interface TimesheetParams {
  month?: string
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

  /** The raw scan log behind one daily record — "necha bora kirib chiqqan". */
  async events(recordId: number): Promise<AttendanceEvent[]> {
    const { data } = await http.get<ApiSuccessResponse<AttendanceEvent[]>>(`/attendance/${recordId}/events`)
    return data.data
  },

  async report(params: AttendanceReportParams): Promise<AttendanceReportRow[]> {
    const { data } = await http.get<ApiSuccessResponse<AttendanceReportRow[]>>('/attendance/report', { params })
    return data.data
  },

  async exportReport(format: 'csv' | 'xlsx' | 'pdf', params: AttendanceReportParams): Promise<void> {
    const response = await http.get('/attendance/report', {
      params: { ...params, export: format },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `attendance-report.${format}`
    link.click()
    URL.revokeObjectURL(url)
  },

  async timesheet(params: TimesheetParams): Promise<Timesheet> {
    const { data } = await http.get<ApiSuccessResponse<Timesheet>>('/attendance/timesheet', { params })
    return data.data
  },

  async exportTimesheet(format: 'csv' | 'xlsx' | 'pdf', params: TimesheetParams): Promise<void> {
    const response = await http.get('/attendance/timesheet', {
      params: { ...params, export: format },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `tabel-${params.month ?? ''}.${format}`
    link.click()
    URL.revokeObjectURL(url)
  },
}
