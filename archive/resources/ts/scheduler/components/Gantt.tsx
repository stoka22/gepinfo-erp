import { useScheduler } from '../store'
import { useMemo } from 'react'
import Timeline from './Timeline'

export default function Gantt() {
  const { from, to, pxPerHour } = useScheduler()

  const gridStyle = useMemo(() => {
    // vékony vonal: minden óra; vastagabb: 6 óránként
    const hour = pxPerHour
    const major = hour * 6
    return {
      backgroundImage: [
        `repeating-linear-gradient(
          to right,
          rgba(255,255,255,0.06) 0,
          rgba(255,255,255,0.06) 1px,
          transparent 1px,
          transparent ${hour}px
        )`,
        `repeating-linear-gradient(
          to right,
          rgba(255,255,255,0.10) 0,
          rgba(255,255,255,0.10) 2px,
          transparent 2px,
          transparent ${major}px
        )`
      ].join(', ')
    } as React.CSSProperties
  }, [pxPerHour])

  return (
    <div className="gantt-root">
      <div className="gantt-scroll" style={{ position:'relative', ...gridStyle }}>
        {/* ... sávok rajzolása ... */}
        <TimeScale from={from} to={to} pxPerHour={pxPerHour} />
      </div>
    </div>
  )
}
function TimeScale({ from, to, pxPerHour }: { from: Date; to: Date; pxPerHour: number }) {
  const hours = Math.max(1, Math.round((to.getTime() - from.getTime()) / 3_600_000))
  const items = new Array(hours + 1).fill(0).map((_, i) => {
    const d = new Date(from.getTime() + i * 3_600_000)
    const left = i * pxPerHour
    const label = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    return <div key={i} style={{ position:'absolute', left, top: 0, transform:'translateX(-50%)', fontSize:12, opacity:.7 }}>
      {label}
    </div>
  })
  return <div style={{ position:'sticky', top:0, height:20, pointerEvents:'none' }}>{items}</div>
}
