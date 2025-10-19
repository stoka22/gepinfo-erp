import { useEffect, useMemo, useState } from 'react'
import { useScheduler } from '../store'
import type { Resource, RowItem } from '../types'

/**
 * ÚJ ResourceTree
 * ----------------
 * Bal oldali oszlop: a cég gépei és munkaállomásai, egyszerű, gyors lista nézetben.
 * - A hierarchiát (partner → order → product → process) elhagyjuk.
 * - Csoportosítás: „Gépek” és „Munkaállomások” (heurisztikus, a Resource.kind / type / group mező alapján).
 * - A Gantt igazításhoz továbbra is beállítjuk a visibleRows tömböt: egy group sor + resource sorok.
 * - Gép soron „+” gomb: azonnal létrehoz egy draft hasábot (createDraftSegment).
 */

type GroupKey = 'machines' | 'workstations'

// Stílusok (egyszerű, letisztult)
const levelStyles = [
  { bg: 'rgba(90,170,255,0.10)',  text: '#cfe6ff', border: 'rgba(90,170,255,0.35)' },   // group
  { bg: 'rgba(255,120,120,0.08)', text: '#ffd4d4', border: 'rgba(255,120,120,0.35)' },  // resource (machine/workstation)
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
    gap: 8,
    width: '100%',
  } as const
}

function classifyResource(r: Resource): GroupKey {
  // Próbálunk okosak lenni, de ha nincs jel, minden „machines” alá kerül.
  const kind = (r as any).kind ?? (r as any).type ?? (r as any).group ?? ''
  const name = (r.name || '').toLowerCase()
  const isWork =
    /workstation|munkaállomás|munkaallomas|állomás|allomas|cell|cella|bench|asztal/.test(kind?.toLowerCase?.() ?? '') ||
    /workstation|munkaállomás|munkaallomas|állomás|allomas|cella|asztal/.test(name)
  return isWork ? 'workstations' : 'machines'
}

export default function ResourceTree() {
  const rowHeight        = useScheduler(s => s.rowHeight)
  const resources        = useScheduler(s => s.resources)
  const setVisibleRows   = useScheduler(s => s.setVisibleRows)
  const setCollapsedRows = useScheduler(s => s.setCollapsedRows)

  // Kereső & szűrők
  const [q, setQ] = useState('')
  const [showMachines, setShowMachines] = useState(true)
  const [showWorkstations, setShowWorkstations] = useState(true)

  // Csoportosítás
  const grouped = useMemo(() => {
    const byGroup: Record<GroupKey, Resource[]> = { machines: [], workstations: [] }
    ;(resources ?? []).forEach(r => {
      const g = classifyResource(r)
      byGroup[g].push(r)
    })
    // rendezés név szerint
    byGroup.machines.sort((a, b) => a.name.localeCompare(b.name))
    byGroup.workstations.sort((a, b) => a.name.localeCompare(b.name))
    return byGroup
  }, [resources])

  // Látható sorok → Gantt igazításhoz
  useEffect(() => {
    const rows: RowItem[] = []
    const collapsed: Record<string, true> = {}

    const pushGroup = (key: GroupKey, label: string) => {
      rows.push({ key: `group:${key}`, kind: 'group', label } as RowItem)
    }
    const pushRes = (r: Resource) => {
      rows.push({ key: `resource:${r.id}`, kind: 'resource', label: r.name, resourceId: Number(r.id) })
    }

    const filterByQ = (r: Resource) => {
      if (!q) return true
      const hay = `${r.name ?? ''} ${(r as any).code ?? ''} ${(r as any).note ?? ''}`.toLowerCase()
      return hay.includes(q.toLowerCase())
    }

    if (showMachines) {
      const list = grouped.machines.filter(filterByQ)
      if (list.length > 0) {
        pushGroup('machines', 'Gépek')
        list.forEach(pushRes)
      }
    }
    if (showWorkstations) {
      const list = grouped.workstations.filter(filterByQ)
      if (list.length > 0) {
        pushGroup('workstations', 'Munkaállomások')
        list.forEach(pushRes)
      }
    }

    setVisibleRows(rows)
    setCollapsedRows(collapsed) // itt nincs összehajtható fa, de a szerkezet megmarad
  }, [grouped, q, showMachines, showWorkstations, setVisibleRows, setCollapsedRows])

  return (
    <div className="space-y-2">
      {/* Fejléc / Szűrők */}
      <div className="flex items-center gap-2 p-2">
        <input
          value={q}
          onChange={e => setQ(e.target.value)}
          placeholder="Keresés..."
          className="w-full px-2 py-1 rounded-md bg-black/10 outline-none"
        />
        <label className="flex items-center gap-1 text-xs opacity-80">
          <input type="checkbox" checked={showMachines} onChange={e => setShowMachines(e.target.checked)} />
          Gépek
        </label>
        <label className="flex items-center gap-1 text-xs opacity-80">
          <input type="checkbox" checked={showWorkstations} onChange={e => setShowWorkstations(e.target.checked)} />
          Munkaállomások
        </label>
      </div>

      {/* Listák */}
      <div className="space-y-2 p-2">
        {showMachines && grouped.machines.length > 0 && (
          <GroupBlock title="Gépek" items={grouped.machines} rowHeight={rowHeight} />
        )}
        {showWorkstations && grouped.workstations.length > 0 && (
          <GroupBlock title="Munkaállomások" items={grouped.workstations} rowHeight={rowHeight} />
        )}
        {showMachines && showWorkstations && grouped.machines.length === 0 && grouped.workstations.length === 0 && (
          <div className="text-sm opacity-70 p-2">Nincs megjeleníthető erőforrás.</div>
        )}
      </div>
    </div>
  )
}

function GroupBlock({ title, items, rowHeight }: { title: string, items: Resource[], rowHeight: number }) {
  return (
    <div>
      <div style={{ height: rowHeight, display: 'flex', alignItems: 'center' }}>
        <div style={rowStyle(0)}>
          <span className="font-semibold">{title}</span>
        </div>
      </div>
      <div className="mt-1 space-y-1">
        {items.map(r => (
          <MachineRow key={r.id} resource={r} rowHeight={rowHeight} />
        ))}
      </div>
    </div>
  )
}

/** Gép / munkaállomás sor (draft + gombbal) */
function MachineRow({ resource, rowHeight }: { resource: Resource, rowHeight: number }) {
  const createDraft = useScheduler(s => s.createDraftSegment)
  const totals = useScheduler(s => s.totals) as Record<number, number> | undefined
  const planned = totals?.[Number(resource.id)] ?? 0

  // célmennyiség-heurisztika
  type ResourceWithTarget = Resource & { targetQty?: number; target_qty?: number; target?: number }
  const rw = resource as ResourceWithTarget
  const target = Number(rw.targetQty ?? rw.target_qty ?? rw.target ?? 0)

  const onAdd = () => {
    const rate = Number((rw as any).defaultRatePph ?? (rw as any).ratePph ?? 100) || 100
    createDraft({
      machineId: Number(resource.id),
      productNodeId: '',   // nincs fa
      processNodeId: '',   // nincs fa
      title: `${resource.name} • 100 db`,
      qty: 100,
      ratePph: rate,
    } as any)
  }

  return (
    <div style={{ paddingLeft: 14, height: rowHeight, display: 'flex', alignItems: 'center' }}>
      <div style={rowStyle(1)}>
        <span className="truncate">
          {resource.name} {`(${Math.round(target)} db / ${Math.round(planned)} db)`}
        </span>
        <button
          type="button"
          className="px-2 py-0.5 rounded-md text-sm hover:opacity-80 border"
          onClick={onAdd}
          title="Hasáb hozzáadása"
        >
          +
        </button>
      </div>
    </div>
  )
}
