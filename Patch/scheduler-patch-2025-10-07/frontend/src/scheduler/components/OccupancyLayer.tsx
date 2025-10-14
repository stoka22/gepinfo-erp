import React, { useEffect, useState } from 'react'
import { fetchOccupancy, OccupancyRange } from '../api'

type Props = {
  resourceId: number
  timelineStartMs: number
  timelineEndMs: number
  pxPerMs: number
}

export default function OccupancyLayer({ resourceId, timelineStartMs, timelineEndMs, pxPerMs }: Props) {
  const [ranges, setRanges] = useState<OccupancyRange[]>([])

  useEffect(() => {
    const fromISO = new Date(timelineStartMs).toISOString()
    const toISO = new Date(timelineEndMs).toISOString()
    fetchOccupancy(resourceId, fromISO, toISO).then(setRanges).catch(() => setRanges([]))
  }, [resourceId, timelineStartMs, timelineEndMs])

  return (
    <div className="absolute inset-0 pointer-events-none">
      {ranges.map((r, i) => {
        const s = new Date(r.start).getTime()
        const e = new Date(r.end).getTime()
        const left = (s - timelineStartMs) * pxPerMs
        const width = Math.max(2, (e - s) * pxPerMs)
        return (
          <div
            key={i}
            className="absolute top-0 h-full"
            style={{
              left,
              width,
              backgroundColor: 'rgba(255,0,0,0.12)',
              border: '1px dashed rgba(255,0,0,0.25)',
            }}
          />
        )
      })}
    </div>
  )
}
