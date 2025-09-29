import React, { useMemo } from 'react'
import { useScheduler } from '../store'
import { TIMELINE_H } from '../utils/constants'

const HOUR_MS = 3_600_000

type BarsLayerProps = {
  /** Eddig a pillanatig visszamenőleg NEM szerkeszthetők a sávok */
  readOnlyBeforeTs?: number
}

export default function BarsLayer({ readOnlyBeforeTs }: BarsLayerProps) {
  const rows      = useScheduler(s => s.visibleRows)
  const tasks     = useScheduler(s => s.tasks)
  const rowHeight = useScheduler(s => s.rowHeight)
  const from      = useScheduler(s => s.from)
  const to        = useScheduler(s => s.to)
  const pxPerHour = useScheduler(s => s.pxPerHour)

  const hourPx   = pxPerHour || 60
  const windowMs = Math.max(1, +to - +from)

  // ⬅ pontos szélesség (NEM kerekítünk egész órára)
  const totalWidth  = (windowMs / HOUR_MS) * hourPx
  const totalHeight = TIMELINE_H + rows.length * rowHeight
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

  const bars = useMemo(() => {
    const baseMs = +from
    const endMsW = +to

    return tasks.map(t => {
      const startMs0 = +new Date(t.start as any)
      const endMs0   = +new Date(t.end as any)

      // Kivágás az ablakra
      const startMs = Math.max(startMs0, baseMs)
      const endMs   = Math.min(endMs0, endMsW)
      if (endMs <= baseMs || startMs >= endMsW) return null

      // ⬅ kulcs resourceId + processNodeId (ha nincs, fallback):
      const pid = (t as any).processNodeId
      if (pid != null && pid !== '') {
        // Van folyamat-konteó → NINCS fallback. Ha a sor nem látható, ne rajzoljunk.
        const key = `${t.resourceId}|${pid}`
        var rowIdx = rowIndexByKey.get(key)
      } else {
        // Régi feladat (nincs processNodeId) → megengedett a fallback
        var rowIdx = rowIndexByKey.get(`${t.resourceId}|`)
      }
      if (rowIdx === undefined) return null

      const left  = msToPx(startMs - baseMs)
      const width = Math.max(2, msToPx(endMs - startMs))
      const top   = TIMELINE_H + rowIdx * rowHeight + 4

      const ro = typeof readOnlyBeforeTs === 'number' && endMs0 <= readOnlyBeforeTs

      return (
        <div
          key={t.id}
          title={`${t.title ?? ''} • ${new Date(startMs0).toLocaleString()} → ${new Date(endMs0).toLocaleString()}`}
          style={{
            position: 'absolute',
            left, top, width,
            height: Math.max(8, rowHeight - 8),
            borderRadius: 6,
            background: ro ? 'rgba(120,120,120,.65)' : 'rgba(56,132,255,.9)',
            border: ro ? '1px dashed rgba(255,255,255,.25)' : '1px solid rgba(0,0,0,.15)',
            boxShadow: '0 1px 3px rgba(0,0,0,.35)',
            overflow: 'hidden',
            whiteSpace: 'nowrap',
            textOverflow: 'ellipsis',
            padding: '0 6px',
            fontSize: 12,
            lineHeight: `${Math.max(8, rowHeight - 8)}px`,
            pointerEvents: ro ? 'none' as const : 'auto',
            zIndex: 2,
          }}
        >
          {t.title}
        </div>
      )
    })
  }, [tasks, from, to, rowHeight, rowIndexByKey, readOnlyBeforeTs, hourPx])

  // Múlt árnyékolása
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
          top: TIMELINE_H,
          width: w,
          height: rows.length * rowHeight,
          background:
            'repeating-linear-gradient(45deg, rgba(255,255,255,.05) 0, rgba(255,255,255,.05) 6px, transparent 6px, transparent 12px)',
          zIndex: 1,
        }}
      />
    )
  })()

  // Most vonal
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
        }}
      />
    )
  })()

  return (
    <div className="relative" style={{ width: totalWidth, height: totalHeight }}>
      {Array.from({ length: rows.length }).map((_, i) => (
        <div
          key={`row-${i}`}
          style={{
            position: 'absolute',
            left: 0, right: 0,
            top: TIMELINE_H + i * rowHeight,
            height: rowHeight,
            borderTop: '1px solid rgba(255,255,255,0.06)',
            zIndex: 0,
          }}
        />
      ))}

      {pastShade}
      {bars}
      {nowLine}
    </div>
  )
}
