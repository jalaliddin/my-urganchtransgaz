import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { AttendanceDevice } from '@/types/models'

export interface AttendanceDevicePayload {
  device_id: string
  name: string
  organization_id?: number | null
}

/** A device with its token — the only response that ever carries one. */
export interface IssuedDeviceToken {
  device: AttendanceDevice
  token: string
}

export const attendanceDeviceService = {
  async list(): Promise<AttendanceDevice[]> {
    const { data } = await http.get<ApiSuccessResponse<AttendanceDevice[]>>('/attendance-devices')
    return data.data
  },

  async create(payload: AttendanceDevicePayload): Promise<IssuedDeviceToken> {
    const { data } = await http.post<ApiSuccessResponse<IssuedDeviceToken>>('/attendance-devices', payload)
    return data.data
  },

  async update(id: number, payload: Partial<Omit<AttendanceDevicePayload, 'device_id'>> & { is_active?: boolean }): Promise<AttendanceDevice> {
    const { data } = await http.patch<ApiSuccessResponse<AttendanceDevice>>(`/attendance-devices/${id}`, payload)
    return data.data
  },

  async regenerateToken(id: number): Promise<IssuedDeviceToken> {
    const { data } = await http.post<ApiSuccessResponse<IssuedDeviceToken>>(`/attendance-devices/${id}/token`)
    return data.data
  },
}
