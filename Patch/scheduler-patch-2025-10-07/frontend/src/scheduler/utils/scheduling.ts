export function hoursBetween(startMs: number, endMs: number) {
  return Math.max(0, (endMs - startMs) / 3600000)
}

export function snapQtyToBatch(rawQty: number, batchSize: number) {
  if (rawQty <= 0) return 0
  if (rawQty < batchSize) return batchSize
  return Math.floor(rawQty / batchSize) * batchSize
}
