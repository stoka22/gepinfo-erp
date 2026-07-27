import React from 'react'
import { useScheduler } from '../store'
import { TIMELINE_H } from '../utils/constants'

export default function ResourceGrid() {
  const rows      = useScheduler(s => s.visibleRows)   // ⬅️ igazodunk a BarsLayer-hez
  const rowHeight = useScheduler(s => s.rowHeight)

  return (
    <div
      style={{
        width: 260,
        borderRight: '1px solid #eee',
        overflow: 'auto',
        paddingTop: TIMELINE_H, // ⬅️ a timeline fejléchez igazít
      }}
    >
      {rows.map((r: any, i: number) => {
        const isGroup   = r?.kind === 'group'
        const isProcess = isGroup && r?.groupType === 'process'
        const key =
          isGroup
            ? `grp:${r.processNodeId ?? r.id ?? i}`
            : `res:${r.resourceId ?? r.id ?? i}`

        // Név/label – óvatos fallback
        const label =
          r?.name ??
          r?.resourceName ??
          r?.title ??
          (isGroup ? 'Csoport' : `Gép #${r?.resourceId ?? '-'}`)

        // Jobb oldali kiegészítő – ha van
        const rightInfo = r?.group ?? r?.code ?? r?.short ?? ''

        return (
          <div
            key={key}
            style={{
              height: rowHeight,
              display: 'flex',
              alignItems: 'center',
              padding: '0 8px',
              borderBottom: '1px solid #f5f5f5',
              background: isGroup
                ? 'rgba(0, 0, 0, 0.03)' // finom háttér a csoport soroknak
                : 'transparent',
              fontWeight: isGroup ? 600 : 500,
              fontSize: isGroup ? 13 : 12,
            }}
            title={label}
          >
            {/* bal oldali indikátor a group soroknak */}
            {isGroup && (
              <div
                style={{
                  width: 4,
                  height: Math.max(10, rowHeight - 12),
                  marginRight: 6,
                  borderRadius: 2,
                  background: isProcess ? 'rgba(255, 195, 85, 0.8)' : 'rgba(160,160,160,0.6)',
                }}
              />
            )}

            <div style={{ fontWeight: isGroup ? 700 : 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {label}
            </div>

            {!!rightInfo && (
              <div style={{ marginLeft: 'auto', fontSize: 11, color: '#777', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {rightInfo}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

