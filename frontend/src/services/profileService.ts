import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { Employee, EmployeeChangeRequest } from '@/types/models'

export interface ProfileUpdatePayload {
  phone?: string | null
  email?: string | null
  address?: string | null
  first_name?: string
  last_name?: string
  middle_name?: string | null
  birth_date?: string | null
  birth_place?: string | null
  gender?: string | null
  passport_number?: string | null
  pinfl?: string | null
  organization_id?: number
  department_id?: number | null
  position_id?: number | null
  employee_number?: string
  hire_date?: string | null
  contacts?: Array<{
    id?: number
    type: string
    full_name: string
    relationship?: string | null
    phone?: string | null
    address?: string | null
    bank_name?: string | null
    bank_account_number?: string | null
  }>
}

export interface ProfileUpdateResult {
  employee: Employee
  change_request: EmployeeChangeRequest | null
}

export interface ProfileCompletion {
  percentage: number
  sections: Record<string, boolean>
  missing_sections: string[]
}

export const profileService = {
  async show(): Promise<Employee> {
    const { data } = await http.get<ApiSuccessResponse<Employee>>('/profile')
    return data.data
  },

  async update(payload: ProfileUpdatePayload): Promise<ProfileUpdateResult> {
    const { data } = await http.put<ApiSuccessResponse<ProfileUpdateResult>>('/profile', payload)
    return data.data
  },

  async completion(): Promise<ProfileCompletion> {
    const { data } = await http.get<ApiSuccessResponse<ProfileCompletion>>('/profile/completion')
    return data.data
  },

  async uploadPhoto(file: File): Promise<Employee> {
    const form = new FormData()
    form.append('photo', file)
    const { data } = await http.post<ApiSuccessResponse<Employee>>('/profile/photo', form)
    return data.data
  },
}
