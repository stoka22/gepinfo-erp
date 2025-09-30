import React, { useEffect, useMemo, useRef, useState } from 'react'
import { useScheduler } from '../store'
import Timeline from './Timeline'
import BarsLayer from './BarsLayer'
import ResourceTree from './ResourceTree'

import { TIMELINE_H } from '../utils/constants'

const MIN_LEFT = 220
const MAX_LEFT = 640
const LS_KEY_LEFT = 'scheduler.leftWidth'
const TOP_SCROLL_H = 12

type ViewMode = 'week' | 'month'
const WEEK_MS  = 7 * 24 * 60 * 60 * 1000
const MONTH_MS = 30 * 24 * 60 * 60 * 1000


export default function SchedulerShell() {
  const from            = useScheduler(s => s.from)
  const to              = useScheduler(s => s.to)
  const loading         = useScheduler(s => s.loading)
  const error           = useScheduler(s => s.error)
  const resources       = useScheduler(s => s.resources)
  const tasks           = useScheduler(s => s.tasks)
  const tree            = useScheduler(s => s.tree)
  const pxPerHour       = useScheduler(s => s.pxPerHour)
  const setPxPerHour    = useScheduler(s => s.setPxPerHour)
  const loadAll         = useScheduler(s => s.loadAll)
  const snapWindowToNow = useScheduler(s => s.snapWindowToNow)
  const setFromTo       = useScheduler(s => s.setFromTo)
  const setWindowToShiftForDate = useScheduler(s => s.setWindowToShiftForDate)
  const shift           = useScheduler(s => s.shift)

  const [view, setView] = useState<ViewMode>('week')
  const viewMs = view === 'week' ? WEEK_MS : MONTH_MS

  // bal oszlop szélesség (persist)
  const [leftWidth, setLeftWidth] = useState<number>(() => {
    const n = Number(localStorage.getItem(LS_KEY_LEFT))
    return Number.isFinite(n) && n >= MIN_LEFT ? Math.min(n, MAX_LEFT) : 300
  })
  useEffect(() => { localStorage.setItem(LS_KEY_LEFT, String(leftWidth)) }, [leftWidth])

  // resizer
  const draggingRef = useRef(false)
  const startXRef   = useRef(0)
  const startWRef   = useRef(leftWidth)

  useEffect(() => {
    const onMove = (e: MouseEvent) => {
      if (!draggingRef.current) return
      const dx = e.clientX - startXRef.current
      const next = Math.min(MAX_LEFT, Math.max(MIN_LEFT, startWRef.current + dx))
      setLeftWidth(next)
      e.preventDefault()
    }
    const onUp = () => {
      if (!draggingRef.current) return
      draggingRef.current = false
      document.body.style.cursor = ''
      document.body.classList.remove('select-none')
    }
    window.addEventListener('mousemove', onMove)
    window.addEventListener('mouseup', onUp)
    return () => {
      window.removeEventListener('mousemove', onMove)
      window.removeEventListener('mouseup', onUp)
    }
  }, [])

  // min. időablak
  useEffect(() => {
    const dur = +to - +from
    if (dur < viewMs) setFromTo(from, new Date(+from + viewMs))
  }, [from, to, viewMs, setFromTo])

  useEffect(() => { loadAll().catch(() => {}) }, [from, to])

  const gridBg = useMemo<React.CSSProperties>(() => {
    const hour = pxPerHour || 60
    const major = hour * 6
    return {
      backgroundImage: [
        `repeating-linear-gradient(to right, rgba(255,255,255,.06) 0, rgba(255,255,255,.06) 1px, transparent 1px, transparent ${hour}px)`,
        `repeating-linear-gradient(to right, rgba(255,255,255,.10) 0, rgba(255,255,255,.10) 2px, transparent 2px, transparent ${major}px)`
      ].join(', ')
    }
  }, [pxPerHour])

  // csak a JOBB oszlop scrollja
  const topScrollRef   = useRef<HTMLDivElement>(null)
  const rightScrollRef = useRef<HTMLDivElement>(null)

  // idősáv szélesség
  const hoursTotal = Math.max(1, (+to - +from) / 3_600_000)
  const contentWidth = Math.ceil(hoursTotal * (pxPerHour || 60))

  // top & main scroll sync
  useEffect(() => {
    const top  = topScrollRef.current
    const main = rightScrollRef.current
    if (!top || !main) return
    let lock = false
    const onTop  = () => { if (lock) return; lock = true; main.scrollLeft = top.scrollLeft;  lock = false }
    const onMain = () => { if (lock) return; lock = true; top.scrollLeft  = main.scrollLeft; lock = false }
    top.addEventListener('scroll', onTop,  { passive: true })
    main.addEventListener('scroll', onMain, { passive: true })
    return () => {
      top.removeEventListener('scroll', onTop)
      main.removeEventListener('scroll', onMain)
    }
  }, [from, to, pxPerHour])

  // ---- Zoom (látható órák beállítása 4h..24h, 0.5h lépések) ----
  const setVisibleHours = (hrs: number) => {
    const clamped = Math.max(4, Math.min(24, Math.round(hrs * 2) / 2)) // 30p lépés
    const container = rightScrollRef.current
    if (!container) return
    const width = container.clientWidth || 1200
    const newPxPerHour = Math.max(10, Math.round(width / clamped))
    setPxPerHour(newPxPerHour)
  }
  // ablakméret változásra tartsuk a zoom-ot vizuálisan
  useEffect(() => {
    const onResize = () => setVisibleHours(Math.max(4, Math.min(24, (rightScrollRef.current?.clientWidth || 1200) / (pxPerHour || 60))))
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [pxPerHour])

  const nowTs = Date.now()
  const shiftWindow = (dir: -1 | 1) => {
    const step = viewMs
    setFromTo(new Date(+from + dir * step), new Date(+to + dir * step))
  }

  const setViewAndSnap = (v: ViewMode) => {
    setView(v)
    const len = v === 'week' ? WEEK_MS : MONTH_MS
    const nf = new Date(Date.now() - len / 2)
    const nt = new Date(Date.now() + len / 2)
    setFromTo(nf, nt)
  }

  // műszak szerint: az aktuális from napjára állítja az ablakot
  const snapToShift = async () => {
    const iso = new Date(from).toISOString().slice(0,10)
    await setWindowToShiftForDate?.(iso)
  }

  return (
    <div className="w-full h-full flex flex-col text-sm">
      <header className="px-3 py-2 border-b border-neutral-700/50 flex items-center gap-3">
        <div className="font-medium text-xl">Gyártástervező</div>

        <div className="flex items-center gap-2 flex-wrap">
          <span className="opacity-70">Gépinfo – Gyártástervező</span>

          <button className={`px-2 py-0.5 text-xs border rounded ${view==='week' ? 'bg-neutral-700/40' : ''}`} onClick={() => setViewAndSnap('week')}>7 nap</button>
          <button className={`px-2 py-0.5 text-xs border rounded ${view==='month' ? 'bg-neutral-700/40' : ''}`} onClick={() => setViewAndSnap('month')}>1 hónap</button>

          <button className="ml-1 px-2 py-0.5 text-xs border rounded" onClick={() => shiftWindow(-1)}>←</button>
          <button className="px-2 py-0.5 text-xs border rounded" onClick={() => shiftWindow(1)}>→</button>

          {/* Zoom – 4..24h, 0.5h step */}
          <div className="flex items-center gap-1 ml-2">
            <span className="text-xs opacity-70">Órák látható:</span>
            <button className="px-2 py-0.5 text-xs border rounded" onClick={() => setVisibleHours(4)}>4h</button>
            <button className="px-2 py-0.5 text-xs border rounded" onClick={() => setVisibleHours(8)}>8h</button>
            <button className="px-2 py-0.5 text-xs border rounded" onClick={() => setVisibleHours(12)}>12h</button>
            <button className="px-2 py-0.5 text-xs border rounded" onClick={() => setVisibleHours(24)}>24h</button>
          </div>

          <div className="flex items-center gap-1">
            <button className="px-2 py-0.5 text-xs border rounded" onClick={snapToShift}>Műszak szerint</button>
            <button className="px-2 py-0.5 text-xs border rounded hover:opacity-80" onClick={() => { snapWindowToNow(); loadAll(); }}>Most köré</button>
          </div>

          <div className="opacity-70">
            {from.toLocaleString()} → {to.toLocaleString()}
          </div>
        </div>

        <div className="ml-auto">
          {loading && <span className="animate-pulse">Betöltés…</span>}
          {!loading && error && <span className="text-red-400">Hiba: {error}</span>}
          {!loading && !error && (
            <span className="opacity-60">
              {resources.length} erőforrás • {tasks.length} sáv • {tree.length} fa-gyökér
            </span>
          )}
        </div>
      </header>

      {/* FŐ GRID – NEM GÖRGET! */}
      <div className="flex-1 min-h-0">
        <div className="grid h-full" style={{ gridTemplateColumns: `${leftWidth}px 6px minmax(0,1fr)` }}>
          {/* Bal fa */}
          <aside className="border-r border-neutral-700/50 bg-neutral-900 min-h-0">
            <div
              className="h-full overflow-y-auto overflow-x-hidden"
              style={{ paddingTop: TOP_SCROLL_H + TIMELINE_H }}
            >
              <ResourceTree />
            </div>
          </aside>

          {/* Resizer */}
          <div
            role="separator"
            aria-orientation="vertical"
            title="Méret módosítása"
            className="cursor-col-resize bg-neutral-700/40 hover:bg-neutral-500/60 transition-colors"
            onMouseDown={(e) => {
              draggingRef.current = true
              startXRef.current = e.clientX
              startWRef.current = leftWidth
              document.body.style.cursor = 'col-resize'
              document.body.classList.add('select-none')
            }}
          />

          {/* Jobb oszlop */}
          <section className="min-h-0 flex flex-col">
            {/* Felső vízszintes scroll csak a jobb oszlopon */}
            <div className="border-b border-neutral-700/50">
              <div ref={topScrollRef} className="overflow-x-auto overflow-y-hidden" style={{ height: TOP_SCROLL_H }}>
                <div style={{ width: contentWidth, height: 1 }} />
              </div>
            </div>

            <div
            ref={rightScrollRef}
            className="flex-1 min-h-0 overflow-auto relative"
            style={{ ...gridBg, paddingTop: TIMELINE_H }}
          >
            {/* Timeline overlay – ne tolja le a tartalmat */}
            <div
              className="sticky top-0 z-10 pointer-events-none"
              style={{ height: TIMELINE_H, marginTop: -TIMELINE_H }}
              aria-hidden
            >
              <Timeline />
            </div>
            <BarsLayer readOnlyBeforeTs={shift?.startTs ?? 0} />
          </div>
          </section>
        </div>
      </div>
    </div>
  )
}
