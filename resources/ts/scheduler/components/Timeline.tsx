import React, { useMemo } from 'react'
import { useScheduler } from '../store'

import { TIMELINE_H } from '../utils/constants'

const BAND_H = TIMELINE_H 
const HOUR_MS = 3_600_000

function pad(n: number) { return n.toString().padStart(2, '0') }

export default function Timeline() {
  const from      = useScheduler(s => s.from)
  const to        = useScheduler(s => s.to)
  const pxPerHour = useScheduler(s => s.pxPerHour)
  const slotMin   = useScheduler(s => s.slotMinutes)

  const hourPx  = pxPerHour || 60
  const slotPx  = (hourPx * Math.max(30, Math.min(120, slotMin ?? 60))) / 60
  const totalMs = Math.max(1, +to - +from)
  const totalH  = Math.ceil(totalMs / HOUR_MS)

  // Nap-fejléc adatok (váltakozó háttérrel)
  const days = useMemo(() => {
    const res: { left: number; width: number; label: string; color: string }[] = []
    const start = new Date(from); start.setHours(0,0,0,0)
    const end   = new Date(to);   end.setHours(24,0,0,0)

    const colors = [
      'linear-gradient(0deg, rgba(35,95,160,.25), rgba(35,95,160,.25))',
      'linear-gradient(0deg, rgba(95,160,35,.25), rgba(95,160,35,.25))',
      'linear-gradient(0deg, rgba(160,95,35,.25), rgba(160,95,35,.25))',
      'linear-gradient(0deg, rgba(120,60,160,.25), rgba(120,60,160,.25))',
    ]

    for (let d = +start, idx = 0; d < +end; d += 86_400_000, idx++) {
      const dayStart = new Date(d)
      const dayEnd   = new Date(d + 86_400_000)
      const left  = Math.max(0, ((+dayStart - +from) / HOUR_MS) * hourPx)
      const right = Math.max(0, ((+dayEnd   - +from) / HOUR_MS) * hourPx)
      const width = Math.max(0, right - left)
      const lab = dayStart.toLocaleDateString(undefined, {
        weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit'
      })
      res.push({ left, width, label: lab, color: colors[idx % colors.length] })
    }
    return res
  }, [from, to, hourPx])

  // Óra cellák (címke középen)
  const hourCells = Array.from({ length: totalH }).map((_, i) => {
    const ts = new Date(+from + i * HOUR_MS)
    return { left: i * hourPx, label: `${pad(ts.getHours())}:00` }
  })

  return (
    <div
      className="sticky top-0 z-10"
      style={{ height: TIMELINE_H, background: 'rgba(20,20,20,.9)', backdropFilter: 'blur(2px)' }}
    >
      {/* Felső nap-fejléc */}
      <div className="relative" style={{ height: BAND_H, borderBottom: '1px solid rgba(255,255,255,.08)' }}>
        {days.map((d, idx) => (
          <div
            key={idx}
            style={{ position: 'absolute', left: d.left, width: d.width, top: 0, bottom: 0 }}
          >
            <div
              style={{
                position: 'absolute', inset: 0, background: d.color,
                borderLeft: '1px solid rgba(255,255,255,.15)'
              }}
            />
           < div
            className="px-1 text-[10px] opacity-90 truncate"
            style={{
              /* EDDIGI: lineHeight ... helyett: */
              height: BAND_H,              // 10px
              display: 'flex',
              alignItems: 'center',        // ⬅ függőleges közép
              justifyContent: 'center',    // vízszintes közép
              textAlign: 'center',
            }}
>
              {d.label}
            </div>
          </div>
        ))}
      </div>

      {/* Alsó órasáv */}
      <div className="relative" style={{ height: BAND_H }}>
        {/* óravonalak */}
        {Array.from({ length: totalH + 1 }).map((_, i) => (
          <div
            key={`line-${i}`}
            style={{
              position: 'absolute', left: i * hourPx, top: 0, bottom: 0,
              borderLeft: '1px solid rgba(255,255,255,.18)'
            }}
          />
        ))}

        {/* óracímkék – a saját cella közepén */}
        {hourCells.map((h, i) => (
          <div
            key={`lbl-${i}`}
            className="text-[10px] px-1 opacity-80 truncate"
            style={{
              position: 'absolute',
              left: h.left,
              width: hourPx,
              height: BAND_H,              // 10px
              display: 'flex',
              alignItems: 'center',        // ⬅ függőleges közép
              justifyContent: 'center',    // vízszintes közép
              textAlign: 'center',
            }}
          >
            {h.label}
          </div>
        ))}

        {/* Félórás jelölés (dashed) */}
        {slotMin === 30 &&
          Array.from({ length: totalH }).map((_, i) => (
            <div
              key={`half-${i}`}
              style={{
                position: 'absolute',
                left: i * hourPx + slotPx,
                top: 0,
                bottom: 0,
                borderLeft: '1px dashed rgba(255,255,255,.10)'
              }}
            />
          ))
        }
      </div>
    </div>
  )
}
