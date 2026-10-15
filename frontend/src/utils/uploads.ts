/**
 * What task attachments, documents and absence orders accept. Mirrors the
 * backend's `config/uploads.php` (checked by extension there too).
 */
export const DOCUMENT_EXTENSIONS = [
  'pdf', 'jpg', 'jpeg', 'png', 'webp',
  'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv',
]

export const DOCUMENT_ACCEPT = DOCUMENT_EXTENSIONS.map((extension) => `.${extension}`).join(',')

export const PHOTO_ACCEPT = 'image/jpeg,image/png'

/**
 * The message to show for a failed upload: the server's first validation
 * error (wrong type, too big, ...), else its message, else `fallback`.
 */
export function uploadErrorMessage(error: unknown, fallback: string): string {
  const response = (error as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } })
    .response
  const firstError = Object.values(response?.data?.errors ?? {})[0]?.[0]

  return firstError ?? (response?.status && response.status < 500 ? response?.data?.message : undefined) ?? fallback
}
