// Általános típusok a schedulerhez

export type Id = number | string

export type NodeType = 'partner' | 'order' | 'product' | 'process' | 'machine'

export type Resource = {
  id: Id
  name: string
  group?: string
  calendarId?: number
}

export type Task = {
  id: Id
  title: string
  start: string // ISO
  end: string   // ISO
  resourceId: Id

  // kapacitás / folyamat
  operationName?: string  // pl. "Lézervágás"
  partnerName?: string
  orderCode?: string
  productSku?: string

  // mennyiség-alapú ütemezéshez
  qtyTotal?: number
  qtyFrom?: number
  qtyTo?: number
  ratePph?: number
  batchSize?: number

  // képességek (mely gépek alkalmasak)
  capableMachineIds?: Array<number | string>

  // megjelenítés
  color?: string | null
  locked?: boolean

  productNodeId?: string;
  processNodeId?: string;
}

export type GroupType = 'partner' | 'order' | 'product' | 'process'

export type RowItem =
  | { key: string; kind: 'group'; label: string; groupType: GroupType }
  | { key: string; kind: 'resource'; label: string; resourceId: number;processNodeId?: string } // ha kell rugalmasság, cseréld Id-re

export interface TreeNode {
  id: string
  type: NodeType
  name: string
  children?: TreeNode[]
  // opcionális pluszok:
  resourceId?: number | string   // machine id, ha van
  sumQty?: number
  sumHours?: number
  hasPlannedBars?: boolean;
}
