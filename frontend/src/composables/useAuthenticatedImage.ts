import { onScopeDispose, ref, watch, type Ref } from 'vue'

import { http } from '@/services/http'

/**
 * Photos and documents are served through authenticated endpoints on a
 * private disk — a plain <img src> can't attach the Bearer token. This
 * fetches the image as a blob and exposes a local object URL instead,
 * revoking it on cleanup to avoid leaking memory.
 */
export function useAuthenticatedImage(url: Ref<string | null | undefined>) {
  const objectUrl = ref<string | null>(null)
  const loading = ref(false)

  async function load(source: string | null | undefined) {
    if (objectUrl.value) {
      URL.revokeObjectURL(objectUrl.value)
      objectUrl.value = null
    }

    if (!source) return

    loading.value = true
    try {
      const response = await http.get(source, { responseType: 'blob' })
      objectUrl.value = URL.createObjectURL(response.data as Blob)
    } catch {
      objectUrl.value = null
    } finally {
      loading.value = false
    }
  }

  watch(url, (value) => load(value), { immediate: true })

  onScopeDispose(() => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
  })

  return { objectUrl, loading }
}
