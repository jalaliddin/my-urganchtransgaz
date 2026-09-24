import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams } from '@/types/api'
import type {
  ActiveStatus,
  EmployeeKpi,
  KpiCalculationType,
  KpiIndicator,
  KpiPeriod,
  KpiPeriodType,
  KpiReportRow,
  KpiTemplate,
} from '@/types/models'

export interface KpiTemplatePayload {
  name: string
  description?: string | null
  organization_id?: number | null
  department_id?: number | null
  status?: ActiveStatus
}

export interface KpiIndicatorPayload {
  name: string
  description?: string | null
  weight: number
  target: number
  measurement_unit?: string | null
  calculation_type: KpiCalculationType
  period: KpiPeriodType
  status?: ActiveStatus
}

export interface KpiPeriodPayload {
  name: string
  period_type: KpiPeriodType
  start_date: string
  end_date: string
  status?: ActiveStatus
}

export interface EmployeeKpiPayload {
  employee_id: number
  kpi_period_id: number
  kpi_indicator_id: number
  target_value?: number
  actual_value?: number | null
  comment?: string | null
}

export interface GeneratePayload {
  kpi_period_id: number
  kpi_template_id: number
  organization_id?: number | null
  department_id?: number | null
}

const templatesBase = createResourceService<KpiTemplate, KpiTemplatePayload, Partial<KpiTemplatePayload>>('/kpi-templates')
const periodsBase = createResourceService<KpiPeriod, KpiPeriodPayload, Partial<KpiPeriodPayload>>('/kpi/periods')
const resultsBase = createResourceService<EmployeeKpi, EmployeeKpiPayload, Partial<EmployeeKpiPayload>>('/kpi')

export const kpiTemplateService = {
  ...templatesBase,

  async indicators(templateId: number): Promise<KpiIndicator[]> {
    const { data } = await http.get<ApiSuccessResponse<KpiIndicator[]>>(`/kpi-templates/${templateId}/indicators`)
    return data.data
  },

  async createIndicator(templateId: number, payload: KpiIndicatorPayload): Promise<KpiIndicator> {
    const { data } = await http.post<ApiSuccessResponse<KpiIndicator>>(`/kpi-templates/${templateId}/indicators`, payload)
    return data.data
  },

  async updateIndicator(templateId: number, indicatorId: number, payload: KpiIndicatorPayload): Promise<KpiIndicator> {
    const { data } = await http.put<ApiSuccessResponse<KpiIndicator>>(
      `/kpi-templates/${templateId}/indicators/${indicatorId}`,
      payload,
    )
    return data.data
  },
}

export const kpiPeriodService = periodsBase

export const kpiService = {
  ...resultsBase,

  async my(params: ListParams = {}): Promise<{ data: EmployeeKpi[]; total: number }> {
    const { data } = await http.get<ApiSuccessResponse<EmployeeKpi[]>>('/kpi/my', { params })
    return { data: data.data, total: data.meta?.total ?? data.data.length }
  },

  async approve(id: number): Promise<EmployeeKpi> {
    const { data } = await http.post<ApiSuccessResponse<EmployeeKpi>>(`/kpi/${id}/approve`)
    return data.data
  },

  async generate(payload: GeneratePayload): Promise<{ created: number }> {
    const { data } = await http.post<ApiSuccessResponse<{ created: number }>>('/kpi/generate', payload)
    return data.data
  },

  async report(periodId: number): Promise<KpiReportRow[]> {
    const { data } = await http.get<ApiSuccessResponse<KpiReportRow[]>>('/kpi/report', { params: { period_id: periodId } })
    return data.data
  },

  async exportReport(periodId: number, format: 'csv' | 'xlsx' | 'pdf'): Promise<void> {
    const response = await http.get('/kpi/report', {
      params: { period_id: periodId, export: format },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `kpi-report.${format}`
    link.click()
    URL.revokeObjectURL(url)
  },
}
