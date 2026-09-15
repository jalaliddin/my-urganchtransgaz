export type OrganizationType = 'central' | 'subordinate'
export type ActiveStatus = 'active' | 'inactive'
export type EmployeeStatus = 'active' | 'vacation' | 'business_trip' | 'sick_leave' | 'inactive' | 'terminated'
export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'internship' | 'temporary'
export type Gender = 'male' | 'female'
export type ContactType = 'emergency' | 'bank'
export type DocumentStatus = 'pending' | 'approved' | 'rejected'
export type ChangeRequestStatus = 'pending' | 'approved' | 'rejected'

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
