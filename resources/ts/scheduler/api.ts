import type { Resource, Task, TreeNode } from './types'

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

/* ---------- Resources ---------- */
export async function fetchResources(): Promise<Resource[]> {
  return fetchJSON<Resource[]>('/api/scheduler/resources')
}

/* ---------- Tasks (list) ---------- */
export async function fetchTasks(params: {
  fromISO: string
  toISO: string
  resourceId?: number | string
}): Promise<Task[]> {
  const { fromISO, toISO, resourceId } = params
  const usp = new URLSearchParams()
  usp.set('from', fromISO)
  usp.set('to', toISO)
  if (resourceId != null) usp.set('resource_id', String(resourceId))
  return fetchJSON<Task[]>(`/api/scheduler/tasks?${usp.toString()}`)
}

/* ---------- Committed task move/resize ---------- */
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

/* ---------- Draft split (create or update) ---------- */
export type SaveSplitBody = {
  id?: string              // "split_{id}" – update esetén
  machine_id: number
  partner_order_item_id?: number | null
  title?: string | null
  start: string            // "YYYY-MM-DDTHH:mm:ss"
  end: string
  ratePph: number          // backend: required, numeric
  batchSize?: number
  qtyFrom?: number
}
export type SaveSplitResp = {
  ok: boolean
  item: Task              // a backend a kliens-sémának megfelelően adja vissza
}

export async function saveSplit(body: SaveSplitBody): Promise<SaveSplitResp> {
  return fetchJSON<SaveSplitResp>('/api/scheduler/splits', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

/* ---------- Tree ---------- */
export async function fetchTree(fromISO: string, toISO: string): Promise<TreeNode[]> {
  const url = new URL('/api/scheduler/tree', window.location.origin)
  url.searchParams.set('from', fromISO)
  url.searchParams.set('to', toISO)
  return fetchJSON<TreeNode[]>(url.toString(), { credentials: 'include' })
}
