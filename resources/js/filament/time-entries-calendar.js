import Alpine from 'alpinejs'

document.addEventListener('alpine:init', () => {
  Alpine.data('timeEntriesCalendar', ({ feedUrl }) => ({
    showSunday: true,
    showSaturday: true,
    showHolidays: false,
    filters: { vacation:true, sick_leave:true, overtime:true, presence:false },
    feedUrl, cal:null, holidaySet:new Set(),
    init(){ /* ugyanaz a FullCalendar-kód, mint fent */ },
    recomputeHolidaysForRange(start,end){ /* ... */ },
  }))
})
