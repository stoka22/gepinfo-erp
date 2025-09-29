export type ShiftPattern = {
  id: number
  name: string
  days_mask: number // bitmask: 1=Mon ... 64=Sun (vagy ahogy a backend tárolja)
  start_time: string // "HH:MM:SS"
  end_time: string   // "HH:MM:SS"
  breaks?: { start_time: string; duration_min: number }[]
}

function parseTime(dateISO: string, time: string) {
  return new Date(`${dateISO}T${time}`)
}

// day -> bit pozíció; igazítsd a saját mentésedhez (itt Mon=1 ... Sun=7)
const dayToBit = (weekday: number) => (1 << ((weekday + 6) % 7)) // Mon=1st bit

export function getShiftWindowForDate(dateISO: string, patterns: ShiftPattern[]) {
  const d = new Date(dateISO + 'T00:00:00')
  const weekday = d.getDay() === 0 ? 7 : d.getDay() // 1..7
  const maskBit = dayToBit(weekday)

  const patt = patterns.find(p => (p.days_mask & maskBit) !== 0)
  if (!patt) return null

  const start = parseTime(dateISO, patt.start_time)
  let   end   = parseTime(dateISO, patt.end_time)
  if (end <= start) end = new Date(+end + 24 * 3600_000) // éjfél átlógás

  return { from: start, to: end }
}
