import React, {
  useEffect,
  useState,
  KeyboardEvent,
  MouseEvent,
  FC,
} from 'react'

export interface Item {
  id: number
  name: string
  sku?: string | null
  unit?: string | null
}

interface ItemPickerModalProps {
  /** Látszódjon-e a modal */
  open: boolean
  /** Bezárás (pl. háttérre vagy X-re kattintáskor) */
  onClose: () => void
  /** Kiválasztott termék visszaadása */
  onSelect: (item: Item) => void
  /** Opcionális előzetes keresőszöveg */
  initialSearch?: string
}

/**
 * Egyszerű felugró termékválasztó.
 * /api/items endpointot hívja (GET), opcionális ?search= paraméterrel.
 */
const ItemPickerModal: FC<ItemPickerModalProps> = ({
  open,
  onClose,
  onSelect,
  initialSearch = '',
}) => {
  const [search, setSearch] = useState(initialSearch)
  const [items, setItems] = useState<Item[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selectedId, setSelectedId] = useState<number | null>(null)

  // ha kinyitod a modalt, töltsük az adatokat
  useEffect(() => {
    if (!open) return
    let cancelled = false

    const fetchItems = async () => {
      setLoading(true)
      setError(null)
      try {
        const params = new URLSearchParams()
        if (search.trim()) {
          params.set('search', search.trim())
        }
        // Szükség szerint módosítsd az URL-t!
        const res = await fetch(`/api/items?${params.toString()}`, {
          credentials: 'include',
        })

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`)
        }

        const data = (await res.json()) as Item[]
        if (!cancelled) {
          setItems(data)
          setSelectedId(data.length ? data[0].id : null)
        }
      } catch (e) {
        console.error(e)
        if (!cancelled) {
          setError('Hiba történt a termékek betöltésekor.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    fetchItems()

    return () => {
      cancelled = true
    }
    // open vagy search változásakor újra lekérdez
  }, [open, search])

  // ESC-re záródjon
  useEffect(() => {
    if (!open) return
    const handler = (ev: KeyboardEvent | any) => {
      if (ev.key === 'Escape') {
        onClose()
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [open, onClose])

  if (!open) return null

  const handleBackgroundClick = (e: MouseEvent<HTMLDivElement>) => {
    // ne zárjuk be, ha a belső dobozra kattintunk
    if (e.target === e.currentTarget) {
      onClose()
    }
  }

  const handleConfirm = () => {
    const item = items.find((it) => it.id === selectedId)
    if (!item) return
    onSelect(item)
    onClose()
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      handleConfirm()
    }
  }

  return (
    <div
      className="item-picker-backdrop"
      onClick={handleBackgroundClick}
      style={{
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(0,0,0,0.45)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 9999,
      }}
    >
      <div
        className="item-picker-modal"
        style={{
          background: '#1f2933',
          color: '#f9fafb',
          borderRadius: 8,
          padding: 16,
          minWidth: 500,
          maxHeight: '70vh',
          display: 'flex',
          flexDirection: 'column',
          boxShadow: '0 10px 30px rgba(0,0,0,0.35)',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <div
          className="item-picker-header"
          style={{
            display: 'flex',
            alignItems: 'center',
            marginBottom: 8,
          }}
        >
          <h2 style={{ flex: 1, fontSize: 18, fontWeight: 600 }}>
            Termék választása
          </h2>
          <button
            type="button"
            onClick={onClose}
            style={{
              border: 'none',
              background: 'transparent',
              color: '#9ca3af',
              cursor: 'pointer',
              fontSize: 18,
            }}
          >
            ✕
          </button>
        </div>

        <div
          className="item-picker-search"
          style={{ marginBottom: 8, display: 'flex', gap: 8 }}
        >
          <input
            type="text"
            placeholder="Keresés név vagy cikkszám alapján…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={handleKeyDown}
            style={{
              flex: 1,
              padding: '6px 8px',
              borderRadius: 4,
              border: '1px solid #4b5563',
              backgroundColor: '#111827',
              color: '#f9fafb',
            }}
          />
        </div>

        <div
          className="item-picker-list"
          style={{
            flex: 1,
            overflowY: 'auto',
            border: '1px solid #374151',
            borderRadius: 4,
            marginBottom: 8,
          }}
        >
          {loading && (
            <div style={{ padding: 8, fontSize: 14 }}>Betöltés…</div>
          )}
          {error && !loading && (
            <div style={{ padding: 8, fontSize: 14, color: '#fca5a5' }}>
              {error}
            </div>
          )}
          {!loading && !error && items.length === 0 && (
            <div style={{ padding: 8, fontSize: 14 }}>Nincs találat.</div>
          )}
          {!loading &&
            !error &&
            items.map((item) => {
              const isSelected = item.id === selectedId
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setSelectedId(item.id)}
                  style={{
                    width: '100%',
                    textAlign: 'left',
                    padding: '6px 8px',
                    border: 'none',
                    borderBottom: '1px solid #374151',
                    backgroundColor: isSelected ? '#2563eb' : 'transparent',
                    color: isSelected ? '#f9fafb' : '#e5e7eb',
                    cursor: 'pointer',
                    fontSize: 14,
                  }}
                >
                  <div style={{ fontWeight: 500 }}>{item.name}</div>
                  <div style={{ fontSize: 11, opacity: 0.8 }}>
                    {item.sku && <span>Cikkszám: {item.sku}</span>}
                    {item.unit && (
                      <span style={{ marginLeft: 8 }}>Egység: {item.unit}</span>
                    )}
                  </div>
                </button>
              )
            })}
        </div>

        <div
          className="item-picker-footer"
          style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}
        >
          <button
            type="button"
            onClick={onClose}
            style={{
              padding: '6px 10px',
              borderRadius: 4,
              border: '1px solid #4b5563',
              background: 'transparent',
              color: '#e5e7eb',
              cursor: 'pointer',
              fontSize: 14,
            }}
          >
            Mégse
          </button>
          <button
            type="button"
            disabled={!selectedId}
            onClick={handleConfirm}
            style={{
              padding: '6px 10px',
              borderRadius: 4,
              border: 'none',
              background: selectedId ? '#10b981' : '#374151',
              color: '#f9fafb',
              cursor: selectedId ? 'pointer' : 'default',
              fontSize: 14,
            }}
          >
            Kiválasztás
          </button>
        </div>
      </div>
    </div>
  )
}

export default ItemPickerModal
