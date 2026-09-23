import { createResourceService } from '@/services/resourceService'
import type { Position } from '@/types/models'

export interface PositionPayload {
  organization_id: number | null
  title: string
  code?: string | null
}

export const positionService = createResourceService<Position, PositionPayload, Partial<PositionPayload>>(
  '/positions',
)
