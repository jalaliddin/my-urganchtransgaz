export type OrganizationType = 'central' | 'subordinate'
export type ActiveStatus = 'active' | 'inactive'
export type EmployeeStatus = 'active' | 'vacation' | 'business_trip' | 'sick_leave' | 'inactive' | 'terminated'
export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'internship' | 'temporary'
export type Gender = 'male' | 'female'

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

  photo: string | null
  status: EmployeeStatus

  created_at: string
  updated_at: string
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
