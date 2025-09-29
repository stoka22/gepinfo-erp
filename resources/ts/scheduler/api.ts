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
    credentials: 'include', // fontos: web+auth session
    headers: { ...baseHeaders, ...(options.headers ?? {}) },
    ...options,
  })

  const text = await resp.text()
  let data: any = null
  try {
    data = text ? JSON.parse(text) : null
  } catch {
    /* ignore invalid JSON */
  }

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
/** from/to kötelező a backend szerint; resourceId opcionális (machine szűrés) */
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

/* ---------- Update schedule (move/resize) ---------- */
/**
 * A meglévő backend végpontok:
 * - POST /api/scheduler/tasks/{id}/move   (machine_id?, starts_at, ends_at, updated_at)
 * - POST /api/scheduler/tasks/{id}/resize (starts_at, ends_at, updated_at)
 */
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
export async function splitTaskByQty(
  _taskId: number | string,
  _payload: any
): Promise<never> {
  throw new Error('A felosztás backend végpontja még nincs implementálva.')
}
/* ---------- (NINCS BACKEND) split végpontok helyett placeholder ----------
   Amíg nincs implementálva a Controller-ben, ezeket NE hívd a UI-ból.
   Ha kéred, a következő körben megírom a backend + itt a hívót.
-------------------------------------------------------------------------- */
// export async function splitTask(...)
// export async function splitTaskByQty(...)

/* ---------- Tree ---------- */
export async function fetchTree(fromISO: string, toISO: string): Promise<TreeNode[]> {
  const url = new URL('/api/scheduler/tree', window.location.origin)
  url.searchParams.set('from', fromISO)
  url.searchParams.set('to', toISO)
  return fetchJSON<TreeNode[]>(url.toString(), { credentials: 'include' })
}
