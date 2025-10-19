import type { Resource, Task, TreeNode } from './types'

/* -------------------- helpers -------------------- */
function getCsrfToken(): string {
  const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
  return m?.content ?? ''
}

async function fetchJSON<T = any>(url: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase()
  const baseHeaders: HeadersInit = {
    'X-Requested-With': 'XMLHttpRequest',
    ...(method !== 'GET'
      ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
      : {}),
  }

  const resp = await fetch(url, {
    credentials: 'include',
    headers: { ...baseHeaders, ...(options.headers ?? {}) },
    ...options,
  })

  const text = await resp.text()
  let data: any = null
  try { data = text ? JSON.parse(text) : null } catch { /* ignore */ }

  if (!resp.ok) {
    const msg = (data && (data.error || data.message)) || text || `HTTP ${resp.status}`
    throw new Error(msg)
  }
  return data as T
}

/* -------------------- resources -------------------- */
export async function fetchResources(): Promise<Resource[]> {
  return fetchJSON<Resource[]>('/api/scheduler/resources')
}

/* -------------------- tasks (list + totals) -------------------- */
export type FetchTasksResult = {
  items: Task[]
  totals: Record<number, number> // { [resourceId]: sumQty }
}

export async function fetchTasks(params: {
  fromISO: string
  toISO: string
  resourceId?: number | string
}): Promise<FetchTasksResult> {
  const { fromISO, toISO, resourceId } = params
  const url = new URL('/api/scheduler/tasks', window.location.origin)
  url.searchParams.set('from', fromISO)
  url.searchParams.set('to', toISO)
  if (resourceId != null) url.searchParams.set('resource_id', String(resourceId))
  // kérjük a szerveroldali összesítést is
  url.searchParams.set('with_totals', '1')

  // Itt nem a fetchJSON-t használjuk, mert a headerből is olvashatunk totals-t
  const resp = await fetch(url.toString(), {
    credentials: 'include',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })

  const text = await resp.text()
  let data: any = null
  try { data = text ? JSON.parse(text) : null } catch { /* ignore */ }

  if (!resp.ok) {
    const msg = (data && (data.error || data.message)) || text || `HTTP ${resp.status}`
    throw new Error(msg)
  }

  // a backend vagy { items, totals }-t ad, vagy csak egy tömböt + X-Scheduler-ResourceTotals header-t
  const headerTotals = resp.headers.get('X-Scheduler-ResourceTotals')
  const items: Task[] = Array.isArray(data?.items) ? data.items : (Array.isArray(data) ? data : [])
  const totals: Record<number, number> = (data && data.totals)
    ? data.totals
    : (headerTotals ? JSON.parse(headerTotals) : {})

  return { items, totals }
}

/* -------------------- committed task move/resize -------------------- */
export async function moveTask(opts: {
  id: number | string
  machineId?: number | string | null
  startsAtISO: string
  endsAtISO: string
  updatedAtISO: string
}): Promise<void> {
  const { id, machineId, startsAtISO, endsAtISO, updatedAtISO } = opts
  await fetchJSON<void>(`/api/scheduler/tasks/${id}/move`, {
    method: 'POST',
    body: JSON.stringify({
      machine_id: machineId ?? null,
      starts_at: startsAtISO,
      ends_at: endsAtISO,
      updated_at: updatedAtISO,
    }),
  })
}

export async function resizeTask(opts: {
  id: number | string
  startsAtISO: string
  endsAtISO: string
  updatedAtISO: string
}): Promise<void> {
  const { id, startsAtISO, endsAtISO, updatedAtISO } = opts
  await fetchJSON<void>(`/api/scheduler/tasks/${id}/resize`, {
    method: 'POST',
    body: JSON.stringify({
      starts_at: startsAtISO,
      ends_at: endsAtISO,
      updated_at: updatedAtISO,
    }),
  })
}

/* -------------------- draft split (create or update) -------------------- */
export type SaveSplitBody = {
  id?: string              // "split_{id}" – update esetén
  machine_id: number
  partner_order_item_id?: number | null
  title?: string | null
  start: string            // ISO
  end: string
  ratePph: number
  batchSize?: number
  qtyFrom?: number
}
export type SaveSplitResp = {
  ok: boolean
  item: Task
}

export async function saveSplit(body: SaveSplitBody): Promise<SaveSplitResp> {
  return fetchJSON<SaveSplitResp>('/api/scheduler/splits', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

/* -------------------- delete (draft / committed) -------------------- */
export async function deleteSplit(splitId: number): Promise<void> {
  await fetchJSON<void>(`/api/scheduler/splits/${splitId}`, { method: 'DELETE' })
}

export async function deleteTask(taskId: number): Promise<void> {
  await fetchJSON<void>(`/api/scheduler/tasks/${taskId}`, { method: 'DELETE' })
}

/* -------------------- next free slot -------------------- */
export async function nextSlot(resourceId: number, fromISO: string, seconds: number): Promise<{ start: string; end: string }> {
  const url = new URL('/api/scheduler/next-slot', window.location.origin)
  url.searchParams.set('resource_id', String(resourceId))
  url.searchParams.set('from', fromISO)
  url.searchParams.set('seconds', String(seconds))
  return fetchJSON<{ start: string; end: string }>(url.toString())
}

/* -------------------- tree -------------------- */
export async function fetchTree(fromISO: string, toISO: string): Promise<TreeNode[]> {
  const url = new URL('/api/scheduler/tree', window.location.origin)
  url.searchParams.set('from', fromISO)
  url.searchParams.set('to', toISO)
  return fetchJSON<TreeNode[]>(url.toString(), { credentials: 'include' })
}
