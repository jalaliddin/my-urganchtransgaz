export type OrganizationType = 'central' | 'subordinate'
export type ActiveStatus = 'active' | 'inactive'
export type EmployeeStatus = 'active' | 'vacation' | 'business_trip' | 'sick_leave' | 'inactive' | 'terminated'
export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'internship' | 'temporary'
export type Gender = 'male' | 'female'
export type ContactType = 'emergency' | 'bank'
export type DocumentStatus = 'pending' | 'approved' | 'rejected'
export type ChangeRequestStatus = 'pending' | 'approved' | 'rejected'
export type AttendanceStatus =
  | 'present'
  | 'late'
  | 'early_leave'
  | 'absent'
  | 'business_trip'
  | 'vacation'
  | 'sick_leave'
export type AttendanceSource = 'biometric' | 'manual' | 'mobile' | 'web' | 'api' | 'system'
export type TaskPriority = 'low' | 'normal' | 'high' | 'urgent'
export type TaskStatus = 'new' | 'in_progress' | 'waiting' | 'completed' | 'cancelled' | 'overdue'
export type ExamStatus = 'draft' | 'active' | 'closed'
export type QuestionType = 'single_choice' | 'multiple_choice' | 'true_false'
export type AttemptStatus = 'in_progress' | 'completed'
export type KpiCalculationType = 'manual' | 'percentage' | 'quantity' | 'rating' | 'formula'
export type KpiPeriodType = 'monthly' | 'quarterly' | 'semiannual' | 'annual'

export interface Organization {
  id: number
  parent_id: number | null
  parent?: Organization | null
  name: string
  short_name: string | null
  code: string
  type: OrganizationType
  address: string | null
  phone: string | null
  email: string | null
  director_name: string | null
  status: ActiveStatus
  departments_count?: number
  employees_count?: number
  created_at: string
  updated_at: string
}

export interface Department {
  id: number
  organization_id: number
  organization?: Organization
  manager_id: number | null
  manager?: Employee | null
  name: string
  short_name: string | null
  code: string
  description: string | null
  status: ActiveStatus
  employees_count?: number
  created_at: string
  updated_at: string
}

export interface Position {
  id: number
  organization_id: number
  title: string
  code: string | null
  status: ActiveStatus
  created_at: string
  updated_at: string
}

export interface Employee {
  id: number
  user_id: number | null
  organization_id: number
  organization?: Organization
  department_id: number | null
  department?: Department | null
  position_id: number | null
  position?: Position | null

  employee_number: string
  first_name: string
  last_name: string
  middle_name: string | null
  full_name: string

  birth_date: string | null
  birth_place: string | null
  gender: Gender | null

  phone: string | null
  email: string | null
  corporate_email: string | null
  address: string | null

  employment_type: EmploymentType | null
  hire_date: string | null
  termination_date: string | null

  photo_url: string | null
  status: EmployeeStatus
  contacts?: EmployeeContact[]

  created_at: string
  updated_at: string
}

export interface EmployeeContact {
  id: number
  type: ContactType
  full_name: string
  relationship: string | null
  phone: string | null
  address: string | null
  bank_name: string | null
  bank_account_number: string | null
}

export interface DocumentType {
  id: number
  name: string
  code: string
  requires_expiry: boolean
  status: ActiveStatus
}

export interface EmployeeDocument {
  id: number
  employee_id: number
  employee?: Employee
  document_type: DocumentType
  title: string
  document_number: string | null
  issue_date: string | null
  expiry_date: string | null
  mime_type: string
  file_size: number
  status: DocumentStatus
  uploaded_by: number
  approved_by: number | null
  approved_at: string | null
  rejection_reason: string | null
  download_url: string
  created_at: string
  updated_at: string
}

export interface EmployeeChangeRequest {
  id: number
  employee_id: number
  employee?: Employee
  changes: Record<string, unknown>
  status: ChangeRequestStatus
  reviewed_by: number | null
  reviewed_at: string | null
  review_comment: string | null
  created_at: string
}

export interface AttendanceRecord {
  id: number
  employee_id: number
  employee?: Employee
  date: string
  check_in: string | null
  check_out: string | null
  worked_minutes: number | null
  status: AttendanceStatus
  source: AttendanceSource
  notes: string | null
  created_at: string
  updated_at: string
}

export interface TodayAttendance {
  employee_id: number
  employee_number: string
  full_name: string
  department: Department | null
  attendance: AttendanceRecord | null
}

export interface AttendanceReportRow {
  group_id: number
  label: string
  total_days: number
  total_worked_minutes: number
  late_count: number
  absent_count: number
  early_leave_count: number
}

export interface TaskComment {
  id: number
  task_id: number
  user_id: number
  user_name: string | null
  body: string
  created_at: string
}

export interface TaskActivity {
  id: number
  action: string
  description: string
  causer_name: string | null
  created_at: string
}

export interface TaskAttachment {
  id: number
  task_id: number
  uploaded_by: number
  original_name: string
  mime_type: string
  file_size: number
  download_url: string
  created_at: string
}

export interface Task {
  id: number
  title: string
  description: string | null
  creator_id: number
  creator_name: string | null
  organization_id: number | null
  organization?: Organization | null
  department_id: number | null
  department?: Department | null

  assignees?: Employee[]

  priority: TaskPriority
  status: TaskStatus

  start_date: string | null
  due_date: string | null
  completed_at: string | null

  progress: number
  result: string | null

  comments?: TaskComment[]
  activities?: TaskActivity[]
  attachments?: TaskAttachment[]

  created_at: string
  updated_at: string
}

export interface ExamAnswer {
  id: number
  answer: string
  is_correct?: boolean
  was_selected?: boolean
}

export interface ExamQuestion {
  id: number
  exam_id?: number
  question: string
  type: QuestionType
  points: number
  order?: number
  answers: ExamAnswer[]
}

export interface ExamAttemptSummary {
  attempts_used: number
  best_percentage: number | null
  passed: boolean
  last_status: AttemptStatus
}

export interface Exam {
  id: number
  title: string
  description: string | null
  organization_id: number | null
  organization?: Organization | null
  department_id: number | null
  department?: Department | null
  created_by: number

  duration_minutes: number
  passing_score: number
  attempts_allowed: number

  start_date: string | null
  end_date: string | null
  status: ExamStatus

  questions_count?: number
  questions?: ExamQuestion[]
  my_attempt_summary?: ExamAttemptSummary | null

  created_at: string
  updated_at: string
}

/**
 * The question shape while taking an exam (ExamAttemptQuestionResource) —
 * has type/points but never is_correct on its answers.
 */
export interface ExamAttemptQuestion {
  id: number
  question: string
  type: QuestionType
  points: number
  answers: Pick<ExamAnswer, 'id' | 'answer'>[]
}

/**
 * The question shape when reviewing a finished attempt
 * (ExamAttemptResource) — no type/points, but answers now reveal
 * is_correct/was_selected.
 */
export interface ExamAttemptQuestionResult {
  id: number
  question: string
  answers: ExamAnswer[]
}

export interface ExamAttempt {
  id: number
  exam_id: number
  employee_id: number

  score: number | null
  percentage: number | null
  passed: boolean | null
  status: AttemptStatus
  is_late: boolean

  started_at: string
  completed_at: string | null

  questions?: ExamAttemptQuestionResult[]
}

export interface ExamResultRow {
  employee_id: number
  full_name: string
  employee_number: string
  attempts_used: number
  best_percentage: number | null
  passed: boolean
  status: 'passed' | 'failed' | 'not_taken'
}

export interface KpiIndicator {
  id: number
  kpi_template_id: number
  name: string
  description: string | null
  weight: number
  target: number
  measurement_unit: string | null
  calculation_type: KpiCalculationType
  period: KpiPeriodType
  status: ActiveStatus
}

export interface KpiTemplate {
  id: number
  name: string
  description: string | null
  organization_id: number | null
  organization?: Organization | null
  department_id: number | null
  department?: Department | null
  status: ActiveStatus
  indicators?: KpiIndicator[]
  created_at: string
  updated_at: string
}

export interface KpiPeriod {
  id: number
  name: string
  period_type: KpiPeriodType
  start_date: string
  end_date: string
  status: ActiveStatus
}

export interface EmployeeKpi {
  id: number
  employee_id: number
  employee?: Employee
  kpi_period_id: number
  period?: KpiPeriod
  kpi_indicator_id: number
  indicator?: KpiIndicator

  target_value: number
  actual_value: number | null
  score: number | null
  weight: number
  weighted_score: number | null
  comment: string | null

  approved_by: number | null
  approved_at: string | null

  created_at: string
  updated_at: string
}

export interface KpiReportRow {
  department_id: number | null
  department_name: string
  employees_count: number
  average_score: number
}

export interface Role {
  id: number
  name: string
}

export type AnnouncementStatus = 'draft' | 'published' | 'archived'
export type AnnouncementTargetType = 'everyone' | 'central' | 'organization' | 'department' | 'employee' | 'role'

export interface AnnouncementTarget {
  id: number
  target_type: AnnouncementTargetType
  target_id: number | null
  label: string
}

export interface Announcement {
  id: number
  title: string
  content: string
  has_image: boolean
  has_attachment: boolean
  attachment_name: string | null
  author_id: number
  author_name: string | null
  priority: TaskPriority
  status: AnnouncementStatus
  publish_at: string | null
  expire_at: string | null
  is_live: boolean
  targets?: AnnouncementTarget[]
  is_read?: boolean
  reads_count?: number
  created_at: string
  updated_at: string
}

export interface AppNotification {
  id: string
  type: string
  title: string | null
  message: string | null
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export interface AuthUser {
  id: number
  name: string
  username: string | null
  email: string
  status: string
  last_login_at: string | null
  roles: string[]
  permissions: string[]
  employee: Employee | null
}
