// ResourceTree.tsx
import { useEffect, useMemo, useState } from 'react'
import { useScheduler } from '../store'
import type { Resource, RowItem, TreeNode } from '../types'

type GroupType = 'partner' | 'order' | 'product' | 'process'

const levelStyles = [
  { bg: 'rgba(90,170,255,0.10)',  text: '#cfe6ff', border: 'rgba(90,170,255,0.35)' },   // partner
  { bg: 'rgba(140,120,255,0.10)', text: '#dcd5ff', border: 'rgba(140,120,255,0.35)' }, // order
  { bg: 'rgba(80,200,120,0.10)',  text: '#cfeede', border: 'rgba(80,200,120,0.35)' },  // product
  { bg: 'rgba(255,195,85,0.10)',  text: '#ffe6bd', border: 'rgba(255,195,85,0.35)' },  // process
  { bg: 'rgba(255,120,120,0.08)', text: '#ffd4d4', border: 'rgba(255,120,120,0.35)' }, // machine
]
const rowStyle = (level: number) => {
  const s = levelStyles[Math.min(level, levelStyles.length - 1)]
  return {
    background: s.bg,
    color: s.text,
    borderLeft: `3px solid ${s.border}`,
    borderRadius: 6,
    padding: '2px 6px',
    display: 'flex',
    alignItems: 'center',
    gap: 6,
    width: '100%',
  } as const
}

export default function ResourceTree() {
  const rowHeight        = useScheduler(s => s.rowHeight)
  const resources        = useScheduler(s => s.resources)
  const tree             = useScheduler(s => s.tree)
  const setVisibleRows   = useScheduler(s => s.setVisibleRows)
  const setCollapsedRows = useScheduler(s => s.setCollapsedRows)

  // Resource lookup Map
  const resById = useMemo(() => {
    const m = new Map<string, Resource>()
    resources.forEach(r => m.set(String(r.id), r))
    return m
  }, [resources])

  // Expand állapot (alapból minden nem-gép nyitva)
  const [expanded, setExpanded] = useState<Set<string>>(new Set())
  useEffect(() => {
    if (!tree || tree.length === 0) return
    const exp = new Set<string>()
    const collect = (n: TreeNode) => {
      if (n.type !== 'machine') exp.add(n.id)
      n.children?.forEach(collect)
    }
    tree.forEach(collect)
    setExpanded(exp)
  }, [tree])

  // Látható sorok → Gantt igazításhoz
  useEffect(() => {
    if (!Array.isArray(tree) || tree.length === 0) {
      setVisibleRows([])
      setCollapsedRows({})
      return
    }

    const rows: RowItem[] = []
    const collapsed: Record<string, true> = {}

    const pushGroupRow = (type: GroupType, label: string) => {
      rows.push({ key: `group:${type}:${label}`, kind: 'group', label, groupType: type })
    }

    const pushResourceRow = (rid: number, processId?: string) => {
      const idStr = String(rid)
      const r = resById.get(idStr)
      rows.push({
        key: `resource:${idStr}:${processId ?? ''}`,
        kind: 'resource',
        label: r?.name ?? `Gép #${idStr}`,
        resourceId: rid,
        processNodeId: processId ?? '',
      })
    }

    const walk = (n: TreeNode) => {
      const isOpen = expanded.has(n.id)

      if (n.type === 'partner') {
        pushGroupRow('partner', n.name)
        if (!isOpen) { collapsed[`group:partner:${n.name}`] = true; return }
        n.children?.forEach(walk)
        return
      }

      if (n.type === 'order') {
        pushGroupRow('order', n.name)
        if (!isOpen) { collapsed[`group:order:${n.name}`] = true; return }
        n.children?.forEach(walk)
        return
      }

      if (n.type === 'product') {
        pushGroupRow('product', n.name)
        if (!isOpen) { collapsed[`group:product:${n.name}`] = true; return }
        n.children?.forEach(walk)
        return
      }

      if (n.type === 'process') {
        pushGroupRow('process', n.name)
        if (!isOpen) { collapsed[`group:process:${n.name}`] = true; return }
        n.children?.forEach(c => {
          if (c.type === 'machine' && c.resourceId != null) {
            pushResourceRow(Number(c.resourceId), n.id)
            //pushResourceRow(Number(c.resourceId))
          }
        })
      }
    }

    tree.forEach(walk)
    setVisibleRows(rows)
    setCollapsedRows(collapsed)
  }, [tree, expanded, resById, setVisibleRows, setCollapsedRows])

  const toggleExpand = (id: string) => {
    const next = new Set(expanded)
    next.has(id) ? next.delete(id) : next.add(id)
    setExpanded(next)
  }

  return (
    <div>
      

      <div style={{ padding: '6px 8px' }}>
        {tree.map(n => (
          <NodeView
            key={n.id}
            node={n}
            level={0}
            expanded={expanded}
            onToggle={toggleExpand}
            rowHeight={rowHeight}
          />
        ))}
      </div>
    </div>
  )
}

function NodeView({
  node, level, expanded, onToggle, rowHeight, productNodeId, productSumQty, processNodeId
}: {
  node: TreeNode
  level: number
  expanded: Set<string>
  onToggle: (id: string) => void
  rowHeight: number
  productNodeId?: string
  productSumQty?: number
  processNodeId?: string
}) {
  const pad = 8 + level * 14
  const isOpen = expanded.has(node.id)

  // GÉP sor
  if (node.type === 'machine') {
    return (
      <div style={{ paddingLeft: pad, height: rowHeight, display: 'flex', alignItems: 'center' }}>
        <div style={rowStyle(level)}>
          <MachineRow
            node={node}
            productNodeId={productNodeId ?? ''}
            processNodeId={processNodeId ?? ''}
          />
        </div>
      </div>
    )
  }

  // FOLYAMAT sor
  if (node.type === 'process') {
    return (
      <div>
        <div
          onClick={() => onToggle(node.id)}
          style={{
            paddingLeft: pad,
            height: rowHeight,
            display: 'flex',
            alignItems: 'center',
            cursor: 'pointer',
            userSelect: 'none',
          }}
        >
          <div style={rowStyle(level)}>
            <span style={{ width: 12, textAlign: 'center' }}>{isOpen ? '▾' : '▸'}</span>
            <ProcessRow node={node} productNodeId={productNodeId ?? ''} productSumQty={productSumQty ?? 0} />
          </div>
        </div>

        {isOpen && node.children?.map(c => (
          <NodeView
            key={c.id}
            node={c}
            level={level + 1}
            expanded={expanded}
            onToggle={onToggle}
            rowHeight={rowHeight}
            productNodeId={productNodeId}
            productSumQty={productSumQty}
            processNodeId={node.id}
          />
        ))}
      </div>
    )
  }

  // PARTNER / ORDER / PRODUCT sorok
  const suffix = (node.sumQty || node.sumHours)
    ? <span style={{ opacity: 0.75, fontWeight: 400, marginLeft: 6 }}>
        ({Math.round(node.sumQty ?? 0)} db, {(node.sumHours ?? 0).toFixed(1)} óra)
      </span>
    : null

  return (
    <div>
      <div
        onClick={() => onToggle(node.id)}
        style={{
          paddingLeft: pad,
          height: rowHeight,
          display: 'flex',
          alignItems: 'center',
          cursor: 'pointer',
          userSelect: 'none',
        }}
      >
        <div style={rowStyle(level)}>
          <span style={{ width: 12, textAlign: 'center' }}>{isOpen ? '▾' : '▸'}</span>
          <span style={{ fontWeight: 600 }} className="truncate">{node.name}</span>{suffix}
        </div>
      </div>

      {isOpen && node.children?.map(c => (
        <NodeView
          key={c.id}
          node={c}
          level={level + 1}
          expanded={expanded}
          onToggle={onToggle}
          rowHeight={rowHeight}
          // product meta továbbadása lefelé
          productNodeId={node.type === 'product' ? node.id : productNodeId}
          productSumQty={node.type === 'product' ? (node.sumQty ?? 0) : productSumQty}
          processNodeId={processNodeId}
        />
      ))}
    </div>
  )
}

/** Gép sor – + gombbal új draft sáv (start=NOW a store-ban) */
function MachineRow({ node, productNodeId, processNodeId, ratePph, defaultQty = 100 }: {
  node: TreeNode
  productNodeId: string
  processNodeId: string
  ratePph?: number
  defaultQty?: number
}) {
  const createDraft = useScheduler(s => s.createDraftSegment)
  const onAdd = () => {
    if (!node.resourceId) return
    createDraft({
      machineId: node.resourceId,
      productNodeId,
      processNodeId,
      title: `${node.name} • ${defaultQty} db`,
      qty: defaultQty,
      ratePph,
    })
  }
  return (
    <>
      <span className="truncate">{node.name}</span>
      <button
        type="button"
        className="px-2 py-0.5 rounded-md text-sm hover:opacity-80 border"
        onClick={onAdd}
        title="Hasáb hozzáadása"
      >
        +
      </button>
      {node.hasPlannedBars && <span className="text-xs opacity-70">(tervezett van)</span>}
    </>
  )
}

/** Folyamat sor – gyártandó / tervezett db */
function ProcessRow({ node, productNodeId, productSumQty }: {
  node: TreeNode
  productNodeId: string
  productSumQty: number
}) {
  const tasks = useScheduler(s => s.tasks)
  const plannedQty = useMemo(
    () => tasks
      .filter(t => t.processNodeId === node.id && t.productNodeId === productNodeId)
      .reduce((acc, t) => acc + (t.qtyTotal ?? 0), 0),
    [tasks, node.id, productNodeId]
  )

  return (
    <>
      <span className="font-medium">{node.name}</span>
      <span className="text-xs opacity-70">
        {Math.round(productSumQty)} db / {plannedQty} db
      </span>
    </>
  )
}
