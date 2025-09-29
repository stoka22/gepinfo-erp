import React from 'react'
import { createRoot } from 'react-dom/client'
import SchedulerShell from './components/SchedulerShell'

// Keress egy wrapper elemet a Blade-ben, pl. <div id="scheduler-root"></div>
const el = document.getElementById('scheduler-root')
if (el) {
  const root = createRoot(el)
  root.render(<SchedulerShell />)
} else {
  // ha nincs target, legalább jelzünk fejlesztéskor
  console.warn('scheduler-root elem nem található a DOM-ban')
}
