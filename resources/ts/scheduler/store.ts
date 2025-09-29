import { create } from 'zustand'
import type { Resource, Task, RowItem, TreeNode } from './types'
import { fetchResources, fetchTasks, fetchTree } from './api'
import { addMinutes, format } from 'date-fns'

type CollapsedMap = Record<string, true>

export type SchedulerState = {
  resources: Resource[]
  tasks: Task[]
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

  /** Igazítás egész órára: from le, to fel a következő egész órára */
  alignWindowToHours: () => void

  /** (opcionális) – műszak tábla alapján állítja az ablakot az adott napra */
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

  createDraftSegment: (opts: {
    machineId: string | number
    productNodeId: string
    processNodeId: string
    title: string
    qty: number
    ratePph?: number
    start?: string
  }) => void
}

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

export const useScheduler = create<SchedulerState>()((set, get) => ({
  resources: [],
  tasks: [],
  tree: [],
  setTree: (t) => {
    const tasks = get().tasks
    set({ tree: annotateTreeWithPlannedBars(t, tasks) })
  },

  from: new Date(),
  to:   new Date(Date.now() + 86400000),
  pxPerHour: 60,
  rowHeight: 28,
  loading: false,
  error: undefined,

  // --- felbontás (30/60 perc)
  slotMinutes: 60,
  setSlotMinutes: (m: number) => set({ slotMinutes: Math.max(30, Math.min(120, Math.round(m))) }),

  setResources: (r) => set({ resources: r }),
  setTasks: (t) => {
    const tree = get().tree
    set({ tasks: t, tree: annotateTreeWithPlannedBars(tree, t) })
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
  const tNeedsAlign =
    t.getMinutes() !== 0 || t.getSeconds() !== 0 || t.getMilliseconds() !== 0
  if (tNeedsAlign) {
    alignedT.setMinutes(0, 0, 0)
    alignedT.setHours(alignedT.getHours() + 1)
  }

  // ⬇️ nincs érdemi változás → ne set-eljünk (különben loop)
  if (+alignedF === +from && +alignedT === +to) return

  set({ from: alignedF, to: alignedT })
},

  // (opcionális) műszak tábla alapján – ha van backend endpointod hozzá
  setWindowToShiftForDate: async (isoDate: string) => {
    try {
      const resp = await fetch(`/api/scheduler/shift-window?date=${isoDate}`)
      if (!resp.ok) return
      const { start, end } = await resp.json() as { start: string; end: string }
      const startDt = new Date(`${isoDate}T${start}`)
      let   endDt   = new Date(`${isoDate}T${end}`)
      if (endDt <= startDt) endDt = new Date(+endDt + 24 * 3600_000) // éjfél átlógás
      set({ from: startDt, to: endDt })
    } catch {
      // csendben elnyeljük – opcionális feature
    }
  },

  async loadAll(opts) {
    const { from, to } = get()
    set({ loading: true, error: undefined })
    try {
      const [resources, tree, tasks] = await Promise.all([
        fetchResources(),
        fetchTree(from.toISOString(), to.toISOString()),
        fetchTasks({
          fromISO: from.toISOString(),
          toISO: to.toISOString(),
          resourceId: opts?.resourceId,
        }),
      ])
      set({
        resources,
        tasks,
        tree: annotateTreeWithPlannedBars(tree, tasks),
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
    set({ tasks: next, tree: annotateTreeWithPlannedBars(tree, next) })
  },

  removeTask: (id) => {
    const next = get().tasks.filter(t => t.id !== id)
    const tree = get().tree
    set({ tasks: next, tree: annotateTreeWithPlannedBars(tree, next) })
  },

  addTasks: (items) => {
    const next = [...get().tasks, ...items]
    const tree = get().tree
    set({ tasks: next, tree: annotateTreeWithPlannedBars(tree, next) })
  },

  visibleRows: [],
  setVisibleRows: (rows) => set({ visibleRows: rows }),

  collapsedRows: {},
  setCollapsedRows: (m) => set({ collapsedRows: m }),

  createDraftSegment: ({ machineId, productNodeId, processNodeId, title, qty, ratePph, start }) => {
    const startDate = start ? new Date(start) : new Date()
    const hours = ratePph && ratePph > 0 ? qty / ratePph : 1
    const endDate = addMinutes(startDate, Math.ceil(hours * 60))

    const task: Task = {
      id: makeId(),
      resourceId: machineId,
      title,
      start: toLocalISO(startDate),
      end: toLocalISO(endDate),
      qtyTotal: qty,
      ratePph,
      productNodeId,
      processNodeId,
    }

    const next = [...get().tasks, task]
    const tree = get().tree
    set({ tasks: next, tree: annotateTreeWithPlannedBars(tree, next) })
  },
}))
