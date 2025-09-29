import { differenceInMinutes } from 'date-fns'

export function timeToX(date: Date, from: Date, pxPerHour: number): number {
  const mins = differenceInMinutes(date, from)
  return (mins / 60) * pxPerHour
}

export function widthBetween(start: Date, end: Date, pxPerHour: number): number {
  const mins = (end.getTime() - start.getTime()) / 60000
  return (mins / 60) * pxPerHour
}

export function pxToMinutes(px: number, pxPerHour: number): number {
  return (px / pxPerHour) * 60
}

// ÚJ: qty ↔ idő konverzió
export function qtyToMinutes(qty: number, ratePph: number): number {
  if (!ratePph) return 0
  return (qty / ratePph) * 60
}

export function minutesToQty(mins: number, ratePph: number): number {
  return (mins / 60) * ratePph
}

export function snapQty(qty: number, batch: number): number {
  if (!batch) return qty
  return Math.round(qty / batch) * batch
}

export function snapMinutes(mins: number, grid = 15) {
  return Math.round(mins / grid) * grid
}

export function clampDate(d: Date, min: Date, max: Date) {
  if (d < min) return new Date(min)
  if (d > max) return new Date(max)
  return d
}