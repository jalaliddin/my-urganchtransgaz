import { ref, watch, type Ref } from 'vue'

import type { ListParams, PaginatedResult } from '@/types/api'

export function usePaginatedResource<T>(fetcher: (params: ListParams) => Promise<PaginatedResult<T>>) {
  const items = ref<T[]>([]) as Ref<T[]>
  const total = ref(0)
  const loading = ref(false)
  const hasError = ref(false)
  const page = ref(1)
  const itemsPerPage = ref(15)
  const search = ref('')

  async function load(): Promise<void> {
    loading.value = true
    hasError.value = false
    try {
      const result = await fetcher({
        page: page.value,
        per_page: itemsPerPage.value,
        ...(search.value ? { 'filter[search]': search.value } : {}),
      })
      items.value = result.data
      total.value = result.meta.total
    } catch {
      hasError.value = true
    } finally {
      loading.value = false
    }
  }

  watch([page, itemsPerPage], load)
  watch(search, () => {
    page.value = 1
    load()
  })

  load()

  return { items, total, loading, hasError, page, itemsPerPage, search, reload: load }
}
