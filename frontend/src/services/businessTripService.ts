import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse } from '@/types/api'
import type { BusinessTrip } from '@/types/models'

export interface BusinessTripPayload {
  employee_id?: number
  destination: string
  purpose?: string | null
  start_date: string
  end_date: string
  order_number?: string | null
}

const base = createResourceService<BusinessTrip, BusinessTripPayload>('/business-trips')

export const businessTripService = {
  ...base,

  async upcoming(): Promise<BusinessTrip[]> {
    const { data } = await http.get<ApiSuccessResponse<BusinessTrip[]>>('/business-trips/upcoming')
    return data.data
  },

  async cancel(id: number): Promise<BusinessTrip> {
    const { data } = await http.post<ApiSuccessResponse<BusinessTrip>>(`/business-trips/${id}/cancel`)
    return data.data
  },
}
