import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams, PaginatedResult } from '@/types/api'

/**
 * A resourceful REST endpoint (index/show/store/update/destroy) shares the
 * same shape across Organizations, Departments, and Employees. This factory
 * avoids re-writing that shape for each one.
 */
export function createResourceService<T, TCreate = Partial<T>, TUpdate = Partial<T>>(
  basePath: string,
) {
  return {
    async list(params: ListParams = {}): Promise<PaginatedResult<T>> {
      const { data } = await http.get<ApiSuccessResponse<T[]>>(basePath, { params })
      return { data: data.data, meta: data.meta! }
    },

    async get(id: number): Promise<T> {
      const { data } = await http.get<ApiSuccessResponse<T>>(`${basePath}/${id}`)
      return data.data
    },

    async create(payload: TCreate): Promise<T> {
      const { data } = await http.post<ApiSuccessResponse<T>>(basePath, payload)
      return data.data
    },

    async update(id: number, payload: TUpdate): Promise<T> {
      const { data } = await http.put<ApiSuccessResponse<T>>(`${basePath}/${id}`, payload)
      return data.data
    },

    async remove(id: number): Promise<void> {
      await http.delete(`${basePath}/${id}`)
    },
  }
}
