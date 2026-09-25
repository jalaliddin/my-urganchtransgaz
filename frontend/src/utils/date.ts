/** "2026-09-24" or an ISO timestamp → "24.09.2026" (the app's DD.MM.YYYY format). */
export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const [year, month, day] = value.slice(0, 10).split('-')
  return `${day}.${month}.${year}`
}

/** An ISO timestamp → "24.09.2026 14:05" in the browser's local time. */
export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Whole days from today to a "YYYY-MM-DD" date: negative once it has passed. */
export function daysUntil(date: string): number {
  const [year, month, day] = date.slice(0, 10).split('-').map(Number)
  const target = new Date(year, month - 1, day)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return Math.round((target.getTime() - today.getTime()) / 86_400_000)
}

/** Today as "YYYY-MM-DD" in local time. */
export function todayIso(offsetDays = 0): string {
  const date = new Date()
  date.setDate(date.getDate() + offsetDays)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
