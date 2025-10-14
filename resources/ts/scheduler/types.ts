export type Id = number | string

export type Resource = {
  id: number
  name: string
}

export type Task = {
  id: Id
  resourceId: number
  title?: string | null
  start: string           // "YYYY-MM-DDTHH:mm:ss"
  end: string
  qtyTotal?: number
  qtyFrom?: number
  qtyTo?: number
  ratePph?: number
  batchSize?: number
  productNodeId?: string
  processNodeId?: string
  committed?: boolean
  updatedAt?: string
}

export type RowItem =
  | { key: string; kind: 'group'; label: string; groupType: 'partner'|'order'|'product'|'process'; processNodeId?: string }
  | { key: string; kind: 'resource'; label: string; resourceId: number; processNodeId?: string }

export type TreeNode = {
  id: string
  type: 'partner' | 'order' | 'product' | 'process' | 'machine'
  name: string
  resourceId?: number
  sumQty?: number
  sumHours?: number
  hasPlannedBars?: boolean
  children?: TreeNode[]
}
