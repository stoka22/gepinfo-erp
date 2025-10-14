// src/scheduler/components/BarsLayer.tsx
import React, { useMemo, useRef } from 'react'
import { useScheduler } from '../store'
import type { Task } from '../types'
import { moveTask, saveSplit } from '../api'

const HOUR_MS  = 3_600_000
const PCS_STEP = 100
const HANDLE_W = 16
const DEFAULT_RATE  = 100
const DEFAULT_BATCH = 100

type BarsLayerProps = {
  /** Eddig a pillanatig visszamenőleg NEM szerkeszthetők a sávok */
  readOnlyBeforeTs?: number
}

// Kerekítés slotra (pl. 30/60 perc)
function roundToSlot(ms: number, slotMs: number) {
  return Math.round(ms / slotMs) * slotMs
}
function toISO19(ms: number) {
  return new Date(ms).toISOString().slice(0, 19)
}

export default function BarsLayer({ readOnlyBeforeTs }: BarsLayerProps) {
  const rows        = useScheduler(s => s.visibleRows)
  const tasks       = useScheduler(s => s.tasks)
  const rowHeight   = useScheduler(s => s.rowHeight)
  const from        = useScheduler(s => s.from)
  const to          = useScheduler(s => s.to)
  const pxPerHour   = useScheduler(s => s.pxPerHour)
  const slotMinutes = useScheduler(s => s.slotMinutes)
  const patchTask   = useScheduler(s => s.patchTask)

  const hourPx   = pxPerHour || 60
  const windowMs = Math.max(1, +to - +from)
  const msPerPx  = HOUR_MS / hourPx
  const slotMs   = (slotMinutes || 60) * 60_000

  const totalWidth  = (windowMs / HOUR_MS) * hourPx
  const totalHeight = rows.length * rowHeight

  const msToPx = (ms: number) => (ms / HOUR_MS) * hourPx

  // resourceId + processNodeId -> rowIndex (és fallback csak resourceId-ra)
  const rowIndexByKey = useMemo(() => {
    const m = new Map<string, number>()
    rows.forEach((r: any, i) => {
      if (r?.kind !== 'resource') return
      const rid = r.resourceId
      const pid = r.processNodeId ?? ''
      m.set(`${rid}|${pid}`, i)
      if (!m.has(`${rid}|`)) m.set(`${rid}|`, i) // fallback első előfordulásra
    })
    return m
  }, [rows])

  // processNodeId -> process group row index (összesítő hasábhoz)
  const processRowIndexById = useMemo(() => {
    const m = new Map<string, number>()
    rows.forEach((r: any, i) => {
      if (r?.kind === 'group' && r?.groupType === 'process' && r?.processNodeId) {
        m.set(String(r.processNodeId), i)
      }
    })
    return m
  }, [rows])

  // -------- Drag & Resize state --------
  const dragRef = useRef<{
    mode: 'move' | 'resize-l' | 'resize-r'
    id: string | number
    start0: number
    end0: number
    qty0: number | undefined
    rate: number | undefined
    x0: number
    active: boolean
  } | null>(null)

  // --- végleges mentés egér felengedéskor
  const persistById = async (id: Task['id']) => {
    // Mindig a LEGFRISSEBB state-ből olvassunk (ne a bezárt closure-ból)
    const all = useScheduler.getState().tasks as Task[]
    const t = all.find(x => x.id === id)
    if (!t) return

    // ideiglenes (még backendre nem mentett) sáv – nincs mit menteni
    if (typeof t.id === 'string' && t.id.startsWith('tmp-')) return

    // committed → move/resize endpoint
    if (typeof t.id === 'number' || (t as any).committed === true) {
      const startsAtISO = (t.start as string).length > 19 ? t.start : `${t.start}:00`
      const endsAtISO   = (t.end   as string).length > 19 ? t.end   : `${t.end}:00`
      await moveTask({
        id: t.id as any,
        machineId: (t as any).resourceId ?? null,
        startsAtISO,
        endsAtISO,
        updatedAtISO: (t as any).updatedAt ?? new Date().toISOString(),
      })
      return
    }

    // draft split → saveSplit (update id-vel)
    if (typeof t.id === 'string' && t.id.startsWith('split_')) {
      await saveSplit({
        id: t.id,
        machine_id: Number(t.resourceId),
        title: t.title ?? null,
        start: t.start,
        end:   t.end,
        ratePph: t.ratePph ?? DEFAULT_RATE,         // sosem undefined
        batchSize: t.batchSize ?? DEFAULT_BATCH,     // sosem undefined
        qtyFrom:  t.qtyFrom ?? 0,
      })
    }
  }

  const startDrag = (
    e: React.MouseEvent,
    mode: 'move' | 'resize-l' | 'resize-r',
    task: any,
    ro: boolean
  ) => {
    if (ro) return
    e.preventDefault()
    e.stopPropagation()

    const start0 = +new Date(task.start as any)
    const end0   = +new Date(task.end as any)

    dragRef.current = {
      mode,
      id: task.id,
      start0,
      end0,
      qty0: task.qtyTotal,
      rate: task.ratePph,
      x0: e.clientX,
      active: true,
    }

    document.body.style.cursor = mode === 'move' ? 'grabbing' : 'ew-resize'
    document.body.classList.add('select-none')

    const onMove = (ev: MouseEvent) => {
      const st = dragRef.current
      if (!st?.active) return
      const dx  = ev.clientX - st.x0
      const dMs = dx * msPerPx

      const dur0 = st.end0 - st.start0
      let newStart = st.start0
      let newEnd   = st.end0
      let newQty   = st.qty0

      if (st.mode === 'move') {
        newStart = roundToSlot(st.start0 + dMs, slotMs)
        newEnd   = newStart + dur0
      } else if (st.mode === 'resize-l') {
        if (st.rate && st.rate > 0) {
          const rawDur  = st.end0 - (st.start0 + dMs)
          const rawQty  = st.rate * (rawDur / HOUR_MS)
          const stepped = Math.max(PCS_STEP, Math.round(rawQty / PCS_STEP) * PCS_STEP)
          newQty        = stepped
          const durMs   = (stepped / st.rate) * HOUR_MS
          newStart      = roundToSlot(st.end0 - durMs, slotMs)
          newEnd        = st.end0
        } else {
          newStart = roundToSlot(st.start0 + dMs, slotMs)
          if (newStart > st.end0 - slotMs) newStart = st.end0 - slotMs
          newEnd = st.end0
        }
      } else if (st.mode === 'resize-r') {
        if (st.rate && st.rate > 0) {
          const rawDur  = (st.end0 + dMs) - st.start0
          const rawQty  = st.rate * (rawDur / HOUR_MS)
          const stepped = Math.max(PCS_STEP, Math.round(rawQty / PCS_STEP) * PCS_STEP)
          newQty        = stepped
          const durMs   = (stepped / st.rate) * HOUR_MS
          newEnd        = roundToSlot(st.start0 + durMs, slotMs)
        } else {
          newEnd = roundToSlot(st.end0 + dMs, slotMs)
          if (newEnd < st.start0 + slotMs) newEnd = st.start0 + slotMs
        }
      }

      const patch: Partial<Task> = { start: toISO19(newStart), end: toISO19(newEnd) }
      if (typeof newQty === 'number') patch.qtyTotal = newQty
      patchTask(st.id as any, patch)
    }

    const onUp = () => {
      const st = dragRef.current
      if (!st?.active) return
      dragRef.current = { ...st, active: false }
      document.body.style.cursor = ''
      document.body.classList.remove('select-none')
      window.removeEventListener('mousemove', onMove)
      window.removeEventListener('mouseup', onUp)

      // végleges mentés
      persistById(task.id).catch(err => {
        console.error('Persist hiba:', err)
      })
    }

    window.addEventListener('mousemove', onMove)
    window.addEventListener('mouseup', onUp)
  }

  const bars = useMemo(() => {
    const baseMs = +from
    const endMsW = +to

    return tasks.map((t: any) => {
      const startMs0 = +new Date(t.start as any)
      const endMs0   = +new Date(t.end as any)

      // Kivágás az ablakra
      const startMs = Math.max(startMs0, baseMs)
      const endMs   = Math.min(endMs0, endMsW)
      if (endMs <= baseMs || startMs >= endMsW) return null

      // kulcs resourceId + processNodeId (ha nincs, fallback)
      const pid = (t as any).processNodeId
      let rowIdx: number | undefined
      if (pid != null && pid !== '') {
        rowIdx = rowIndexByKey.get(`${t.resourceId}|${pid}`)
      } else {
        rowIdx = rowIndexByKey.get(`${t.resourceId}|`)
      }
      if (rowIdx === undefined) return null

      const left  = msToPx(startMs - baseMs)
      const width = Math.max(2, msToPx(endMs - startMs))
      const top   = rowIdx * rowHeight + 4

      // Csak TELJESEN múltbeli sáv read-only
      const ro =
        typeof readOnlyBeforeTs === 'number' &&
        endMs0 <= readOnlyBeforeTs

      const barH        = Math.max(8, rowHeight - 8)
      const baseColor   = ro ? 'rgba(120,120,120,.65)' : 'rgba(56,132,255,.9)'
      const edgeShade   = 'rgba(0,0,0,0.36)'
      const minBarWidth = HANDLE_W * 2 + 12

      // Látható „resize zónák” a háttérben
      const background = ro
        ? baseColor
        : `linear-gradient(to right,
              ${edgeShade} 0,
              ${edgeShade} ${HANDLE_W}px,
              transparent ${HANDLE_W + 1}px,
              transparent calc(100% - ${HANDLE_W + 1}px),
              ${edgeShade} calc(100% - ${HANDLE_W}px),
              ${edgeShade} 100%
           ), ${baseColor}`

      return (
        <div
          key={t.id}
          title={`${t.title ?? ''} • ${new Date(startMs0).toLocaleString()} → ${new Date(endMs0).toLocaleString()}`}
          style={{
            position: 'absolute',
            left, top,
            width,
            minWidth: minBarWidth,
            height: barH,
            borderRadius: 6,
            background,
            backgroundClip: 'padding-box',
            border: ro ? '1px dashed rgba(255,255,255,.25)' : '1px solid rgba(0,0,0,.15)',
            boxShadow: '0 1px 3px rgba(0,0,0,.35)',
            overflow: 'hidden',
            whiteSpace: 'nowrap',
            textOverflow: 'ellipsis',
            padding: '0 6px',
            fontSize: 12,
            lineHeight: `${barH}px`,
            pointerEvents: 'auto',
            zIndex: 2,
            cursor: ro ? 'default' : 'grab',
            userSelect: 'none',
          }}
          onMouseDown={(e) => {
            if (ro) return
            const rect = (e.currentTarget as HTMLDivElement).getBoundingClientRect()
            const x = e.clientX - rect.left
            const nearLeft  = x <= HANDLE_W
            const nearRight = rect.width - x <= HANDLE_W
            if (nearLeft)        startDrag(e, 'resize-l', t, ro)
            else if (nearRight)  startDrag(e, 'resize-r', t, ro)
            else                 startDrag(e, 'move', t, ro)
          }}
          onMouseMove={(e) => {
            if (ro) return
            const rect = (e.currentTarget as HTMLDivElement).getBoundingClientRect()
            const x = e.clientX - rect.left
            const overEdge = x <= HANDLE_W || rect.width - x <= HANDLE_W
            ;(e.currentTarget as HTMLDivElement).style.cursor = overEdge ? 'ew-resize' : 'grab'
          }}
        >
          {t.title}
        </div>
      )
    })
  }, [tasks, from, to, rowHeight, rowIndexByKey, readOnlyBeforeTs, pxPerHour, slotMinutes])

  // Összesítő hasáb a process sorokon
  const processAggregates = useMemo(() => {
    const baseMs = +from
    const endMsW = +to

    const group: Record<string, { min: number, max: number }> = {}
    for (const t of tasks as any[]) {
      const pid = t.processNodeId
      if (!pid) continue
      const s = +new Date(t.start as any)
      const e = +new Date(t.end as any)
      if (!(pid in group)) group[pid] = { min: Number.POSITIVE_INFINITY, max: Number.NEGATIVE_INFINITY }
      if (s < group[pid].min) group[pid].min = s
      if (e > group[pid].max) group[pid].max = e
    }

    const nodes: React.ReactElement[] = []
    for (const [pid, range] of Object.entries(group)) {
      const idx = processRowIndexById.get(String(pid))
      if (idx === undefined) continue
      const s = Math.max(range.min, baseMs)
      const e = Math.min(range.max, endMsW)
      if (e <= baseMs || s >= endMsW) continue

      const left  = msToPx(s - baseMs)
      const width = Math.max(2, msToPx(e - s))
      const top   = idx * rowHeight + (rowHeight - 6)

      nodes.push(
        <div
          key={`agg-${pid}`}
          title={`Összesítő: ${new Date(range.min).toLocaleString()} → ${new Date(range.max).toLocaleString()}`}
          style={{
            position: 'absolute',
            left, top, width,
            height: 6,
            borderRadius: 3,
            background: 'rgba(255, 195, 85, 0.55)',
            border: '1px solid rgba(255, 195, 85, 0.8)',
            boxShadow: '0 1px 2px rgba(0,0,0,.25)',
            zIndex: 1 as any,
            pointerEvents: 'none',
          }}
        />
      )
    }
    return nodes
  }, [tasks, from, to, rowHeight, processRowIndexById, pxPerHour])

  // Múlt árnyékolása – a sávok alatt (zIndex:1)
  const pastShade = (() => {
    if (typeof readOnlyBeforeTs !== 'number') return null
    const start = +from
    const end   = +to
    if (readOnlyBeforeTs <= start) return null
    const shadeRightEdge = Math.min(readOnlyBeforeTs, end)
    const w = Math.max(0, msToPx(shadeRightEdge - start))
    if (w <= 0) return null
    return (
      <div
        key="past-shade"
        style={{
          position: 'absolute',
          left: 0,
          top: 0,
          width: w,
          height: rows.length * rowHeight,
          background:
            'repeating-linear-gradient(45deg, rgba(255,255,255,.05) 0, rgba(255,255,255,.05) 6px, transparent 6px, transparent 12px)',
          zIndex: 1,
          pointerEvents: 'none',
        }}
      />
    )
  })()

  // Most vonal – legfelül (zIndex:3)
  const nowLine = (() => {
    const now = Date.now()
    if (now < +from || now > +to) return null
    const x = msToPx(now - +from)
    return (
      <div
        key="now-line"
        style={{
          position: 'absolute',
          left: x,
          top: 0,
          bottom: 0,
          width: 2,
          background: 'rgba(255,255,255,.55)',
          boxShadow: '0 0 0 1px rgba(0,0,0,.25) inset',
          zIndex: 3,
          pointerEvents: 'none',
        }}
      />
    )
  })()

  return (
    <div
      className="relative"
      style={{ width: totalWidth, height: totalHeight, pointerEvents: 'auto' }}
    >
      {Array.from({ length: rows.length }).map((_, i) => (
        <div
          key={`row-${i}`}
          style={{
            position: 'absolute',
            left: 0, right: 0,
            top: i * rowHeight,
            height: rowHeight,
            borderTop: '1px solid rgba(255,255,255,0.06)',
            zIndex: 0,
            pointerEvents: 'none',
          }}
        />
      ))}

      {pastShade}
      {processAggregates}
      {bars}
      {nowLine}
    </div>
  )
}
