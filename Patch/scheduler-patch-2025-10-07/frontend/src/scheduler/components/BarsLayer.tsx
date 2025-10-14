import React, { useMemo, useCallback } from 'react'
import { useScheduler } from '../store'
import { saveSplit } from '../api'
import { hoursBetween, snapQtyToBatch } from '../utils/scheduling'

const HOUR_MS = 3_600_000

type BarsLayerProps = {
  readOnlyBeforeTs?: number
}

export default function BarsLayer({ readOnlyBeforeTs }: BarsLayerProps) {
  const { items, setItems, timelineStartMs, pxPerMs } = useScheduler()

  // Called when a new bar is dropped on a row
  const onCreateBar = useCallback(async (resourceId: number, startMs: number, endMs: number) => {
    const draft = {
      resourceId,
      title: 'Új művelet',
      start: new Date(startMs).toISOString(),
      end: new Date(endMs).toISOString(),
      ratePph: 100,       // fallback, UI-ból érdemes beolvasni
      batchSize: 100,
      qtyFrom: 0,
    }
    const saved = await saveSplit(draft)
    setItems((prev) => [...prev, saved])
  }, [setItems])

  // Called when user resizes an existing bar
  const onResizeBar = useCallback(async (id: string, newStartMs: number, newEndMs: number) => {
    const item = items.find(i => i.id === id)
    if (!item) return
    const rate = item.ratePph ?? 0
    const batch = item.batchSize ?? 100
    const rawQty = Math.floor(hoursBetween(newStartMs, newEndMs) * rate)
    const qty = snapQtyToBatch(rawQty, batch)

    const updated = await saveSplit({
      ...item,
      start: new Date(newStartMs).toISOString(),
      end: new Date(newEndMs).toISOString(),
      qtyFrom: item.qtyFrom,
      batchSize: batch,
      ratePph: rate,
    })

    // optimistic local update
    setItems((prev) => prev.map(p => p.id === id ? { ...updated, qtyTotal: qty, qtyTo: (updated.qtyFrom ?? 0) + qty } : p))
  }, [items, setItems])

  // Render is app-specific; here we only expose the handlers via context or props in your app.
  // Hook these into your existing drag/drop & resize logic.
  return null
}
