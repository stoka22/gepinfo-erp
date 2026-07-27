import { useEffect, useMemo, useState } from 'react'
import { useScheduler } from '../store'
import type { Resource, RowItem } from '../types'
import { TOP_SCROLL_H, TIMELINE_H } from '../utils/constants'
import ItemPickerModal, { Item } from './ItemPickerModal'

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
    <div>
      {/* Fejléc / Szűrők */}



      {/* Listák */}
      <div className=" p-2">
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
      <div >
        {items.map(r => (
          <MachineRow key={r.id} resource={r} rowHeight={rowHeight} />
        ))}
      </div>
    </div>
  )
}

/** Gép / munkaállomás sor (draft + gombbal) */
/** Gép / munkaállomás sor (draft + gombbal) */
/** Gép / munkaállomás sor (ikonos gombokkal) */
function MachineRow({ resource, rowHeight }: { resource: Resource, rowHeight: number }) {
  const createDraft = useScheduler(s => s.createDraftSegment)
  const totals      = useScheduler(s => s.totals) as Record<number, number> | undefined
  const planned     = totals?.[Number(resource.id)] ?? 0

  // csak a + gombhoz kell modál
  const [isPickerOpen, setPickerOpen] = useState(false)

  type ResourceWithTarget = Resource & { targetQty?: number; target_qty?: number; target?: number }
  const rw     = resource as ResourceWithTarget
  const target = Number(rw.targetQty ?? rw.target_qty ?? rw.target ?? 0)

  const baseRate =
    Number((rw as any).defaultRatePph ?? (rw as any).ratePph ?? 100) || 100

  // közös: honnan induljon az új hasáb (utolsó vége vagy most)
  const calcStartISO = () => {
    const allTasks = useScheduler.getState().tasks as any[]
    const machineIdNum = Number(resource.id)

    const lastEndMs = allTasks
      .filter(t => Number(t.resourceId) === machineIdNum)
      .reduce((max, t) => {
        const ms = +new Date(t.end as any)
        return Number.isFinite(ms) && ms > max ? ms : max
      }, 0)

    const now = new Date()
    const startDate = lastEndMs > now.getTime() ? new Date(lastEndMs) : now
    return startDate.toISOString()
  }

  // ➕ Munkafolyamat – itt KELL a modál (termék választás)
  const onAddWorkflow = () => {
    setPickerOpen(true)
  }

  // modálban kiválasztott termék → normál munkafolyamat hasáb
  const handleItemSelected = (item: Item) => {
    const qty     = 300
    const ratePph = baseRate
    const startISO = calcStartISO()

    const title = `${item.name} – ${item.sku} - Dolgozó`

    createDraft({
      machineId: Number(resource.id),
      productNodeId: String(item.id), // ide megy a termék azonosító
      processNodeId: '',
      title,
      qty,
      ratePph,
      start: startISO,
    } as any)

    setPickerOpen(false)
  }

  // 🛠 / ⚙ speciális hasábok – NEM kell modál
  const createServiceDraft = (type: 'maintenance' | 'setup') => {
    const qty     = 300
    const ratePph = baseRate
    const startISO = calcStartISO()

    let title: string
    if (type === 'maintenance') {
      title = `${resource.name} – Javítás`
    } else {
      title = `${resource.name} – Beállítás`
    }

    createDraft({
      machineId: Number(resource.id),
      productNodeId: '',   // ezekhez nem választunk terméket
      processNodeId: '',
      title,
      qty,
      ratePph,
      start: startISO,
    } as any)
  }

  const onAddMaintenance = () => createServiceDraft('maintenance')
  const onAddSetup       = () => createServiceDraft('setup')

  const iconBtn =
    'w-7 h-7 flex items-center justify-center rounded-md text-xs border hover:opacity-80';

  return (
    <div style={{ paddingLeft: 14, height: rowHeight, display: 'flex', alignItems: 'center' }}>
      <div style={rowStyle(1)}>
        <span className="truncate">
          {resource.name} {`(${Math.round(target)} db / ${Math.round(planned)} db)`}
        </span>

        <div className="flex items-center gap-1 ml-auto">
          {/* Munkafolyamat hozzáadása – plusz ikon (MODÁL) */}
          <button
            type="button"
            className={`${iconBtn} bg-neutral-800/70`}
            onClick={onAddWorkflow}
            title="Munkafolyamat hozzáadása"
          >
            ＋
          </button>

          {/* Javítás – piros ikonos gomb (NINCS modál) */}
          <button
            type="button"
            className={iconBtn}
            style={{
              backgroundColor: 'rgba(248,113,113,0.9)',
              borderColor: 'rgba(127,29,29,0.9)',
              color: '#fff',
            }}
            onClick={onAddMaintenance}
            title="Javítás hasáb hozzáadása"
          >
            🛠
          </button>

          {/* Beállítás – sárga ikonos gomb (NINCS modál) */}
          <button
            type="button"
            className={iconBtn}
            style={{
              backgroundColor: 'rgba(252,211,77,0.95)',
              borderColor: 'rgba(180,83,9,0.9)',
              color: '#000',
            }}
            onClick={onAddSetup}
            title="Beállítás hasáb hozzáadása"
          >
            ⚙
          </button>
        </div>
      </div>

      {/* csak a normál munkafolyamathoz használt termékválasztó */}
      <ItemPickerModal
        open={isPickerOpen}
        onClose={() => setPickerOpen(false)}
        onSelect={handleItemSelected}
      />
    </div>
  )
}
