import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams } from '@/types/api'
import type { AuditLog, ReportOverview, SearchGroup, SettingsResponse, SettingsValues } from '@/types/models'

export const settingsService = {
  async get(): Promise<SettingsResponse> {
    const { data } = await http.get<ApiSuccessResponse<SettingsResponse>>('/settings')
    return data.data
  },

  async update(values: Partial<SettingsValues>): Promise<void> {
    await http.put('/settings', { values })
  },

  async uploadLogo(file: File): Promise<void> {
    const form = new FormData()
    form.append('logo', file)
    await http.post('/settings/logo', form)
  },

  logoUrl(): string {
    return '/settings/logo'
  },
}

export const auditLogService = {
  async list(params: ListParams = {}): Promise<{ data: AuditLog[]; total: number }> {
    const { data } = await http.get<ApiSuccessResponse<AuditLog[]>>('/audit-logs', { params })
    return { data: data.data, total: data.meta?.total ?? data.data.length }
  },

  /**
   * Audit logs live behind an authenticated endpoint (Bearer token, not
   * a cookie), so a plain <a href>/window.open can't fetch it — fetch
   * as a blob through the same http instance and trigger the save from
   * that, same pattern as documentService.download.
   */
  async export(format: 'csv' | 'xlsx' | 'pdf', params: Record<string, string | number | undefined> = {}): Promise<void> {
    const response = await http.get('/audit-logs', { params: { ...params, export: format }, responseType: 'blob' })
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `audit-logs.${format}`
    link.click()
    URL.revokeObjectURL(url)
  },
}

export const searchService = {
  async search(q: string): Promise<SearchGroup[]> {
    const { data } = await http.get<ApiSuccessResponse<SearchGroup[]>>('/search', { params: { q } })
    return data.data
  },
}

export const reportService = {
  async overview(): Promise<ReportOverview> {
    const { data } = await http.get<ApiSuccessResponse<ReportOverview>>('/reports/overview')
    return data.data
  },
}
