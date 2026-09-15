import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { AuthUser } from '@/types/models'

export interface LoginPayload {
  login: string
  password: string
}

export interface LoginResult {
  token: string
  user: AuthUser
}

export const authService = {
  async login(payload: LoginPayload): Promise<LoginResult> {
    const { data } = await http.post<ApiSuccessResponse<LoginResult>>('/auth/login', payload)
    return data.data
  },

  async logout(): Promise<void> {
    await http.post('/auth/logout')
  },

  async me(): Promise<AuthUser> {
    const { data } = await http.get<ApiSuccessResponse<AuthUser>>('/auth/me')
    return data.data
  },

  async changePassword(payload: {
    current_password: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await http.post('/auth/change-password', payload)
  },

  async forgotPassword(email: string): Promise<void> {
    await http.post('/auth/forgot-password', { email })
  },

  async resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await http.post('/auth/reset-password', payload)
  },
}
