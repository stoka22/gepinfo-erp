import { useScheduler } from '../store'

export default function ResourceGrid() {
  const { resources, rowHeight } = useScheduler()
  return (
    <div style={{ width: 260, borderRight: '1px solid #eee', overflow: 'auto' }}>
      {resources.map((r) => (
        <div key={r.id}
             style={{ height: rowHeight, display: 'flex', alignItems: 'center', padding: '0 8px', borderBottom: '1px solid #f5f5f5' }}>
          <div style={{ fontWeight: 600 }}>{r.name}</div>
          {r.group && <div style={{ marginLeft: 'auto', fontSize: 12, color: '#777' }}>{r.group}</div>}
        </div>
      ))}
    </div>
  )
}
