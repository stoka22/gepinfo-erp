import create from 'zustand'
import { SchedulerItem } from './api'

type State = {
  items: SchedulerItem[]
  setItems: (updater: (prev: SchedulerItem[]) => SchedulerItem[]) => void
  timelineStartMs: number
  timelineEndMs: number
  pxPerMs: number
}

export const useScheduler = create<State>((set, get) => ({
  items: [],
  setItems: (updater) => set({ items: updater(get().items) }),
  timelineStartMs: Date.now(),
  timelineEndMs: Date.now() + 12 * 3600000,
  pxPerMs: 1 / (5 * 60 * 1000), // 1px per 5 minutes as example
}))
