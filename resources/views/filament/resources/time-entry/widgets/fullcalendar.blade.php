<x-filament-widgets::widget>
  <x-filament::section>
    <div
       x-data="timeEntriesCalendar({
        feedUrl:    @js(route('time-entries.calendar.events')),
        markersUrl: @js(route('time-entries.calendar.markers')),
        })"
        x-init="mount()"
        wire:ignore
        >
      {{-- KAPCSOLÓK – most már a komponensen belül vannak --}}
      <div class="flex flex-wrap items-center gap-4 text-sm mb-3 p-3">
        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-rose-500/80"    x-model="showSunday">    Vasárnap</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-amber-500/80"   x-model="showSaturday"> Szombat (pihenőnap)</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-sky-500/80"     x-model="showHolidays"> Ünnepnap</label>

        <span class="mx-3 opacity-60">|</span>

        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-amber-500/80"   x-model="filters.vacation">   Szabadság</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-rose-500/80"    x-model="filters.sick_leave"> Táppénz</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-sky-500/80"     x-model="filters.overtime">   Túlóra</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-emerald-500/80" x-model="filters.presence">   Jelenlét</label>
      </div>

      {{-- NAPTÁR --}}
      <div x-ref="cal" style="height: 340px"></div>
    </div>
  </x-filament::section>

  <style>
    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number,
    .fc .fc-toolbar-title { color:#0f5af2 !important; }
    .fc-theme-standard .fc-scrollgrid, .fc-theme-standard td, .fc-theme-standard th { border-color:rgba(255,255,255,.10); }
    .fc-sunday-cell   { background: rgba(239, 68, 68, .18); }
    .fc-saturday-cell { background: rgba(234,179,  8, .18); }
    .fc-holiday-cell  { background: rgba( 59,130,246, .20); }
    .fc .fc-daygrid-day.fc-day-today { outline: 2px solid rgba(59,130,246,.35); }
    .fc .fc-more-popover .fc-popover-body { background: rgba(0,0,0,.85); color: #e5e7eb; }
    .fc-workday-cell { background: rgba(234,179,8,.22); }
    .fc-restday-cell  { background: rgba(99,102,241,.20); }
    .fc-sunday-cell{background:rgba(239,68,68,.18)}
    .fc-saturday-cell{background:rgba(234,179,8,.18)}
    .fc-holiday-cell{background:rgba(251, 4, 4, 0.348)}
    .fc-workday-cell{background:rgba(16,185,129,.20)}
    .fc-restday-cell{background:rgba(168,85,247,.20)}
  </style>
</x-filament-widgets::widget>
