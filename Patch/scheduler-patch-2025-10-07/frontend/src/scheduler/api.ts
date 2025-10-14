export type SchedulerItem = {
  id: string
  resourceId: number
  title: string
  start: string
  end: string
  qtyTotal: number
  qtyFrom: number
  qtyTo: number
  ratePph: number
  batchSize: number
  committed?: boolean
}

export async function saveSplit(item: Partial<SchedulerItem>) {
  const res = await fetch('/api/scheduler/splits', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: item.id,
      machine_id: item.resourceId,
      title: item.title,
      start: item.start,
      end: item.end,
      ratePph: item.ratePph,
      batchSize: item.batchSize,
      qtyFrom: item.qtyFrom ?? 0,
    }),
  })
  if (!res.ok) throw new Error('Failed to save split')
  return (await res.json()).item as SchedulerItem
}

export type OccupancyRange = { start: string; end: string }
export async function fetchOccupancy(resourceId: number, fromISO: string, toISO: string) {
  const url = new URL('/api/scheduler/occupancy', window.location.origin)
  url.searchParams.set('from', fromISO)
  url.searchParams.set('to', toISO)
  url.searchParams.set('resource_id', String(resourceId))
  const res = await fetch(url)
  if (!res.ok) throw new Error('Failed to load occupancy')
  const data = await res.json()
  return data.ranges as OccupancyRange[]
}
