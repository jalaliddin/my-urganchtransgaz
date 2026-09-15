export interface ApiSuccessResponse<T> {
  success: true
  message: string
  data: T
  meta?: PaginationMeta
}

export interface ApiErrorResponse {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginatedResult<T> {
  data: T[]
  meta: PaginationMeta
}

export interface ListParams {
  page?: number
  per_page?: number
  'filter[search]'?: string
  sort?: string
  [key: string]: string | number | undefined
}
