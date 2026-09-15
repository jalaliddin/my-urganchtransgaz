import { createResourceService } from '@/services/resourceService'
import type { Organization, OrganizationType } from '@/types/models'

export interface OrganizationPayload {
  parent_id?: number | null
  name: string
  short_name?: string | null
  code: string
  type: OrganizationType
  director_name?: string | null
  address?: string | null
  phone?: string | null
  email?: string | null
}

export const organizationService = createResourceService<Organization, OrganizationPayload, Partial<OrganizationPayload>>(
  '/organizations',
)
