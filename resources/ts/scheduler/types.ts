export type Resource = {
  id: number | string
  name: string
}

export type Task = {
  id: string | number
  resourceId: number | string
  title?: string
  start: string // ISO
  end: string   // ISO
  qtyTotal?: number
  ratePph?: number
  productNodeId?: string
  processNodeId?: string
}

export type TreeNode = {
  id: string
  type: 'partner' | 'order' | 'product' | 'process' | 'machine'
  name: string
  children?: TreeNode[]
  resourceId?: number
  hasPlannedBars?: boolean
  // opcionális aggregált meta
  sumQty?: number
  sumHours?: number
}

export type RowItem =
  | {
      key: string
      kind: 'group'
      label: string
      groupType: 'partner' | 'order' | 'product' | 'process'
      /** process csoportsorhoz: a node id-ja, hogy a BarsLayer be tudja lőni az összesítőt */
      processNodeId?: string
    }
  | {
      key: string
      kind: 'resource'
      label: string
      resourceId: number
      /** melyik process alá tartozik a gép sor */
      processNodeId: string
    }
