import type { AbsenceType } from '@/types/models'

export const ABSENCE_TYPES: AbsenceType[] = [
  'annual_leave',
  'unpaid_leave',
  'study_leave',
  'maternity_leave',
  'childcare_leave',
  'sick_leave',
  'business_trip',
  'other',
]

export type AbsenceCategory = 'vacation' | 'business_trip' | 'sick_leave' | 'excused'

/** Mirrors AbsenceType::attendanceStatus() on the backend. */
export function absenceCategory(type: AbsenceType): AbsenceCategory {
  if (type === 'sick_leave' || type === 'business_trip') return type
  if (type === 'other') return 'excused'
  return 'vacation'
}

/** The tabel letter each category is printed as (AttendanceStatus::shortCode). */
export const CATEGORY_CODE: Record<AbsenceCategory, string> = {
  vacation: 'T',
  business_trip: 'X',
  sick_leave: 'B',
  excused: 'S',
}

export const CATEGORY_COLOR: Record<AbsenceCategory, string> = {
  vacation: 'info',
  business_trip: 'primary',
  sick_leave: 'warning',
  excused: 'absence-other',
}

export const STATE_COLOR: Record<string, string> = {
  upcoming: 'info',
  current: 'success',
  completed: 'default',
  cancelled: 'error',
}

/** Calendar days, both ends inclusive — how leave is counted. */
export function inclusiveDays(start: string, end: string): number {
  if (!start || !end) return 0
  const diff = (Date.parse(end) - Date.parse(start)) / 86_400_000
  return diff < 0 ? 0 : Math.round(diff) + 1
}

export function toIsoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}
