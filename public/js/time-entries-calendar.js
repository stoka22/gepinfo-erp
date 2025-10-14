// public/js/time-entries-calendar.js
// Feltételezi, hogy: 1) Alpine.js már betöltött, 2) FullCalendar index.global.min.js defer-rel be van húzva.

document.addEventListener('alpine:init', () => {
  const pad = n => String(n).padStart(2, '0');
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const onlyYmd = s => (s ? String(s).slice(0, 10) : s);

  Alpine.data('timeEntriesCalendar', ({ feedUrl, markersUrl }) => ({
    // --- Kapcsolók (UI) ---
    showSunday: true,
    showSaturday: true,
    showHolidays: true,
    showWorkdayOverrides: true,
    showRestOverrides: true,

    // esemény típus szűrők
    filters: { vacation: true, sick_leave: true, overtime: true, presence: false },

    // --- Állapot ---
    feedUrl, markersUrl,
    cal: null,
    markers: new Map(),

    // Látható napcellákra felhelyezi az osztályokat a markers Map alapján (FC v5 kompat)
    applyMarkersToCells() {
      if (!this.$refs.cal) return;
      // v5 dayGrid: .fc-daygrid-day[data-date="YYYY-MM-DD"]
      const cells = this.$refs.cal.querySelectorAll('.fc-daygrid-day');
      cells.forEach(cell => {
        const key = cell.getAttribute('data-date'); // pl. "2025-10-23"
        if (!key) return;

        // először töröljük a korábbi jelöléseket (idempotens frissítés)
        cell.classList.remove('fc-holiday-cell', 'fc-workday-cell', 'fc-restday-cell', 'fc-sunday-cell', 'fc-saturday-cell');

        // hétvége jelölései
        const d = new Date(key + 'T00:00:00');
        if (this.showSunday && d.getDay() === 0) cell.classList.add('fc-sunday-cell');
        if (this.showSaturday && d.getDay() === 6) cell.classList.add('fc-saturday-cell');

        // marker alapú jelölések
        const marks = this.markers.get(key) || [];
        for (const m of marks) {
          if (m.kind === 'holiday' && this.showHolidays)        cell.classList.add('fc-holiday-cell');
          if (m.kind === 'workday' && this.showWorkdayOverrides) cell.classList.add('fc-workday-cell');
          if (m.kind === 'restday'  && this.showRestOverrides)   cell.classList.add('fc-restday-cell');
          if (m.title) cell.title = (cell.title ? cell.title + '\n' : '') + m.title;
        }
      });
    },

    // Jelölők (ünnepnap / áthelyezett napok) betöltése a back-endről
    async loadMarkers(range) {
      const p = new URLSearchParams({ start: range.startStr, end: range.endStr });
      const res = await fetch(`${this.markersUrl}?${p.toString()}`, { credentials:'include' });
      if (!res.ok) {
        console.error('Marker fetch failed', res.status, await res.text());
        this.markers = new Map();
        this.applyMarkersToCells();
        return;
      }
      const data = await res.json();
      const m = new Map();
      data.forEach(x => {
        const key = onlyYmd(x.date); // "YYYY-MM-DD"
        if (!key) return;
        if (!m.has(key)) m.set(key, []);
        m.get(key).push({ kind: x.kind, title: x.title });
      });
      this.markers = m;

      // v5: nincs rerenderDates → kézzel frissítjük a DOM-ot
      this.applyMarkersToCells();
    },

    // FullCalendar inicializálása
    mount() {
      const boot = () => {
        const Calendar = window.FullCalendar?.Calendar;
        if (!Calendar) return setTimeout(boot, 30);

        this.cal = new Calendar(this.$refs.cal, {
          initialView: 'dayGridMonth',
          height: 'auto',
          contentHeight: 360,
          firstDay: 1,
          locale: 'hu',
          headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
          dayMaxEvents: 4,
          dayMaxEventRows: true,

          events: (info, ok, fail) => {
            const types = Object.entries(this.filters).filter(([, on]) => on).map(([k]) => k);
            const p = new URLSearchParams({ start: info.startStr, end: info.endStr });
            types.forEach(t => p.append('types[]', t));
            fetch(`${this.feedUrl}?${p.toString()}`, { credentials: 'include' })
              .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
              .then(ok).catch(fail);
          },

          datesSet: async (range) => {
            await this.loadMarkers(range);   // markers frissítése minden nézetváltáskor
            this.applyMarkersToCells();      // biztos ami biztos
          },

          // Hagyhatjuk, de nem erre támaszkodunk a frissítésnél
          dayCellDidMount: (arg) => {
            // csak első renderkor lefutó inicial jelölés
            const key = ymd(arg.date);
            const d = arg.date;
            if (this.showSunday && d.getDay() === 0) arg.el.classList.add('fc-sunday-cell');
            if (this.showSaturday && d.getDay() === 6) arg.el.classList.add('fc-saturday-cell');

            const marks = this.markers.get(key) || [];
            for (const m of marks) {
              if (m.kind === 'holiday' && this.showHolidays)        arg.el.classList.add('fc-holiday-cell');
              if (m.kind === 'workday' && this.showWorkdayOverrides) arg.el.classList.add('fc-workday-cell');
              if (m.kind === 'restday'  && this.showRestOverrides)   arg.el.classList.add('fc-restday-cell');
              if (m.title) arg.el.title = (arg.el.title ? arg.el.title + '\n' : '') + m.title;
            }
          },

          eventDidMount: (info) => {
            const p = info.event.extendedProps || {};
            info.el.title = `${info.event.title}\nStátusz: ${p.status ?? '-'}\nMegjegyzés: ${p.note ?? '-'}`;
          },
        });

        this.cal.render();

        // watcherek – v5: manuális DOM frissítés kell
        this.$watch('filters',      () => this.cal?.refetchEvents(), { deep: true });
        this.$watch('showSunday',   () => this.applyMarkersToCells());
        this.$watch('showSaturday', () => this.applyMarkersToCells());
        this.$watch('showHolidays', () => this.applyMarkersToCells());
        this.$watch('showWorkdayOverrides', () => this.applyMarkersToCells());
        this.$watch('showRestOverrides',    () => this.applyMarkersToCells());
      };
      boot();
    },
  }));
});
