// src/scheduler/store.ts
import { create } from 'zustand'
import type { Resource, Task, RowItem, TreeNode } from './types'
import { fetchResources, fetchTasks, fetchTree, saveSplit, nextSlot } from './api'
import { format } from 'date-fns'

const DEFAULT_RATE  = 100

type CollapsedMap = Record<string, true>
type ShiftWindow = { startTs: number; endTs: number }


export type SchedulerState = {
  resources: Resource[]
  tasks: Task[]
  /** Szerveroldali vagy kliens által számolt összesített darabszám erőforrásonként (resourceId → qty) */
  totals: Record<number, number>

  tree: TreeNode[]
  setTree: (t: TreeNode[]) => void

  setResources: (r: Resource[]) => void
  setTasks: (t: Task[]) => void

  from: Date
  to: Date
  pxPerHour: number
  rowHeight: number
  loading: boolean
  error?: string

  /** 30 vagy 60 – a rács és a snap felbontása */
  slotMinutes: number
  setSlotMinutes: (m: number) => void

  /** Időablak beállítása egy lépésben */
  setFromTo: (from: Date, to: Date) => void
  /** (legacy) – kompatibilitás kedvéért marad */
  setWindow: (from: Date, to: Date) => void
  setPxPerHour: (v: number) => void

  /** Egész órára igazítás: from le, to fel a következő egész órára */
  alignWindowToHours: () => void

  /** Műszak-ablak (DB-ből) az aktuális nézethez */
  shift?: ShiftWindow

  /** (opcionális) – műszak tábla alapján állítja az ablakot és a shift state-et az adott napra */
  setWindowToShiftForDate?: (isoDate: string) => Promise<void>

  loadAll: (opts?: { resourceId?: number | string }) => Promise<void>
  snapWindowToNow: () => void

  patchTask: (id: Task['id'], patch: Partial<Task>) => void
  removeTask: (id: Task['id']) => void
  addTasks: (items: Task[]) => void

  visibleRows: RowItem[]
  setVisibleRows: (rows: RowItem[]) => void
  collapsedRows: CollapsedMap
  setCollapsedRows: (m: CollapsedMap) => void

  /** Új draft sáv létrehozása ÉS azonnali mentése `splits`-be */
  createDraftSegment: (opts: {
    machineId: string | number
    productNodeId: string
    processNodeId: string
    title?: string
    qty: number
    ratePph?: number
    /** Kiindulási időpont a kereséshez; ha nincs, az aktuális ablak eleje */
    start?: string
    /** Csak akkor küldjük, ha megadtad – nincs implicit 100 */
    batchSize?: number
  }) => Promise<void>
}

/* ---------------- helpers ---------------- */
function makeId() {
  try {
    return `tmp-${(crypto as any)?.randomUUID?.() ?? Math.random().toString(36).slice(2)}`
  } catch {
    return `tmp-${Math.random().toString(36).slice(2)}`
  }
}
function toLocalISO(d: Date) {
  return format(d, "yyyy-MM-dd'T'HH:mm:ss")
}
function startOfHour(d = new Date()) { const x = new Date(d); x.setMinutes(0,0,0); return x }
function addHours(d: Date, h: number) { return new Date(d.getTime() + h * 3_600_000) }

/** gép node megjelölése, ha tartozik hozzá sáv */
function annotateTreeWithPlannedBars(tree: TreeNode[], tasks: Task[]): TreeNode[] {
  const plannedByMachine = new Set(tasks.map(t => String(t.resourceId)))
  const walk = (n: TreeNode): TreeNode => {
    const isMachine = n.type === 'machine'
    const hasPlannedBars = isMachine ? plannedByMachine.has(String(n.resourceId)) : n.hasPlannedBars
    return { ...n, hasPlannedBars, children: n.children?.map(walk) }
  }
  return tree.map(walk)
}

/** elfogad "HH:mm[:ss]" vagy teljes ISO-t is */
function parseShiftEdge(edge: string, isoDate: string) {
  if (/[Tt]/.test(edge) || /[Z\+\-]\d{2}:?\d{2}$/.test(edge)) return new Date(edge)
  const hhmmss = edge.length <= 5 ? `${edge}:00` : edge
  return new Date(`${isoDate}T${hhmmss}`)
}

/** kliens oldali totals újraszámolás a jelenlegi tasks tömbből */
function recomputeTotals(tasks: Task[]): Record<number, number> {
  const map: Record<number, number> = {}
  for (const t of tasks) {
    const rid = Number((t as any).resourceId)
    const q = Number((t as any).qtyTotal ?? 0)
    if (!Number.isFinite(rid)) continue
    map[rid] = (map[rid] ?? 0) + (Number.isFinite(q) ? q : 0)
  }
  return map
}

/* ---------------- store ---------------- */
export const useScheduler = create<SchedulerState>()((set, get) => ({
  resources: [],
  tasks: [],
  totals: {},

  tree: [],
  setTree: (t) => {
    const tasks = get().tasks
    set({ tree: annotateTreeWithPlannedBars(t, tasks) })
  },

  from: new Date(),
  to:   new Date(Date.now() + 86400000),
  pxPerHour: 60,
  rowHeight: 40,
  loading: false,
  error: undefined,

  // --- felbontás (30/60 perc)
  slotMinutes: 60,
  setSlotMinutes: (m: number) => set({ slotMinutes: Math.max(30, Math.min(120, Math.round(m))) }),

  setResources: (r) => set({ resources: r }),
  setTasks: (t) => {
    const tree = get().tree
    set({
      tasks: t,
      tree: annotateTreeWithPlannedBars(tree, t),
      totals: recomputeTotals(t), // ⬅ mindig legyen konzisztens
    })
  },

  setWindow: (from, to) => set({ from, to }),
  setPxPerHour: (v) => set({ pxPerHour: v }),

  setFromTo: (from: Date, to: Date) => {
    set({ from: new Date(from), to: new Date(to) })
  },

  // egész órára igazítás
  alignWindowToHours: () => {
    const { from, to } = get()
    const f = new Date(from)
    const t = new Date(to)
    const alignedF = new Date(f); alignedF.setMinutes(0, 0, 0)
    const alignedT = new Date(t)
    const tNeedsAlign = t.getMinutes() !== 0 || t.getSeconds() !== 0 || t.getMilliseconds() !== 0
    if (tNeedsAlign) {
      alignedT.setMinutes(0, 0, 0)
      alignedT.setHours(alignedT.getHours() + 1)
    }
    if (+alignedF === +from && +alignedT === +to) return
    set({ from: alignedF, to: alignedT })
  },

  // (opcionális) műszak tábla alapján
  setWindowToShiftForDate: async (isoDate: string) => {
    try {
      const resp = await fetch(`/api/scheduler/shift-window?date=${isoDate}`)
      if (!resp.ok) return
      const { start, end } = (await resp.json()) as { start: string; end: string }
      const startDt = parseShiftEdge(start, isoDate)
      let   endDt   = parseShiftEdge(end,   isoDate)
      if (+endDt <= +startDt) endDt = new Date(+endDt + 24 * 3600_000)
      set({ from: startDt, to: endDt, shift: { startTs: +startDt, endTs: +endDt } })
    } catch { /* optional feature */ }
  },

    async loadAll(opts) {
    const { from, to } = get()
    set({ loading: true, error: undefined })
    try {
      const [resources, tree, tasksResp] = await Promise.all([
        fetchResources(),
        fetchTree(from.toISOString(), to.toISOString()),
        fetchTasks({
          fromISO: from.toISOString(),
          toISO: to.toISOString(),
          resourceId: opts?.resourceId,
        }),
      ])

      const prev = get().tasks
      const stitchMeta = (it: any) => {
        const p = prev.find(x => String(x.id) === String(it.id))
        return {
          ...it,
          productNodeId: it.productNodeId ?? p?.productNodeId ?? '',
          processNodeId: it.processNodeId ?? p?.processNodeId ?? '',
        }
      }

      const items = (tasksResp.items ?? []).map(stitchMeta)
      const totals = tasksResp.totals ?? {}

      set({
        resources,
        tasks: items,
        totals: Object.keys(totals).length ? totals : (items.reduce((m: any, t: any) => {
          const rid = Number(t.resourceId); const q = Number(t.qtyTotal ?? 0)
          if (Number.isFinite(rid)) m[rid] = (m[rid] ?? 0) + (Number.isFinite(q) ? q : 0)
          return m
        }, {})),
        tree: annotateTreeWithPlannedBars(tree, items),
        loading: false,
      })
    } catch (e: any) {
      console.error('Scheduler load error:', e)
      set({ loading: false, error: e?.message ?? 'Betöltési hiba' })
    }
  },


  snapWindowToNow: () => {
    const now = startOfHour(new Date())
    set({ from: addHours(now, -6), to: addHours(now, 24) })
  },

  patchTask: (id, patch) => {
    const next = get().tasks.map(t => (t.id === id ? { ...t, ...patch } : t))
    const tree = get().tree
    set({
      tasks: next,
      tree: annotateTreeWithPlannedBars(tree, next),
      totals: recomputeTotals(next), // ⬅ patch után is frissítsük az összesítést
    })
  },

  removeTask: (id) => {
    const next = get().tasks.filter(t => t.id !== id)
    const tree = get().tree
    set({
      tasks: next,
      tree: annotateTreeWithPlannedBars(tree, next),
      totals: recomputeTotals(next), // ⬅ törlés után is
    })
  },

  addTasks: (items) => {
    const next = [...get().tasks, ...items]
    const tree = get().tree
    set({
      tasks: next,
      tree: annotateTreeWithPlannedBars(tree, next),
      totals: recomputeTotals(next), // ⬅ hozzáadás után is
    })
  },

  visibleRows: [],
  setVisibleRows: (rows) => set({ visibleRows: rows }),

  collapsedRows: {},
  setCollapsedRows: (m) => set({ collapsedRows: m }),

  /** Új draft sáv + azonnali mentés (következő SZABAD slotba) */
  createDraftSegment: async ({ machineId, productNodeId, processNodeId, title, qty, ratePph, start, batchSize }) => {
    const rate = (ratePph ?? DEFAULT_RATE)
    const hours = rate > 0 ? qty / rate : 1
    const minutes = Math.ceil(hours * 60)
    const seconds = Math.max(60, Math.ceil(minutes * 60))

    // 1) kérjük le a KÖVETKEZŐ SZABAD ABLAKOT a backendtől
    const searchFromISO = start ?? get().from.toISOString()
    const slot = await nextSlot(Number(machineId), searchFromISO, seconds)
    const startDate = new Date(slot.start)
    const endDate   = new Date(slot.end)

    // 2) optimista lokális sáv (stackelés helyett a slotban)
    const tmpId = makeId()
    const local: Task = {
      id: tmpId,
      resourceId: Number(machineId),
      title: (title ?? 'Tervezett művelet') + ` • ${qty} db`,
      start: toLocalISO(startDate),
      end:   toLocalISO(endDate),
      qtyTotal: qty,
      qtyFrom: 0,
      qtyTo: qty,
      ratePph: rate,
      ...(batchSize != null ? { batchSize } : {}),
      productNodeId,
      processNodeId,
      committed: false,
    }
    const nextLocal = [...get().tasks, local]
    set({
      tasks: nextLocal,
      tree: annotateTreeWithPlannedBars(get().tree, nextLocal),
      totals: recomputeTotals(nextLocal), // ⬅ azonnali frissítés
    })

    // 3) backend mentés
    try {
      const res = await saveSplit({
        machine_id: Number(machineId),
        title: local.title ?? null,
        start: local.start,
        end:   local.end,
        ratePph: rate,
        ...(batchSize != null ? { batchSize } : {}),
        qtyFrom: local.qtyFrom ?? 0,
      })

      // 4) csere a visszaadott elemmel (lokális meta megőrzésével)
      const server = res.item
      const replaced: Task = {
        ...server,
        productNodeId,
        processNodeId,
      }
      const tasksNow = get().tasks
      const idx = tasksNow.findIndex(t => t.id === tmpId)
      let arr: Task[]
      if (idx >= 0) {
        arr = tasksNow.slice()
        arr[idx] = replaced
      } else {
        arr = [...tasksNow, replaced]
      }
      set({
        tasks: arr,
        tree: annotateTreeWithPlannedBars(get().tree, arr),
        totals: recomputeTotals(arr), // ⬅ pontos totals a végleges adatok alapján
      })
    } catch (e) {
      console.error('Split mentés hiba:', e)
      // opcionálisan vissza lehetne venni a tmp sort; most meghagyjuk vizuális jelzésnek
    }
  },
}))
