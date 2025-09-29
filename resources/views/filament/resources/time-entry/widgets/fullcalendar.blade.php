<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-3 mb-3 text-sm">
            <span class="inline-flex items-center gap-2">
                <span class="w-3 h-3 rounded bg-[rgba(239,68,68,.18)] ring-1 ring-white/20 inline-block"></span>
                Vasárnap
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="w-3 h-3 rounded bg-[rgba(234,179,8,.18)] ring-1 ring-white/20 inline-block"></span>
                Szombat (pihenőnap)
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="w-3 h-3 rounded bg-[rgba(59,130,246,.20)] ring-1 ring-white/20 inline-block"></span>
                Ünnepnap
            </span>
        </div>

        <div
            x-data="{ eventsUrl: @js(route('admin.time-entries.calendar.events')) }"
            x-init="$nextTick(() => {
                const el = $refs.cal;
                if (!el || el.dataset.mounted === '1') return;

                const FC_CSS = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css';
                const FC_JS  = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js';

                const ensureFullCalendar = (cb) => {
                    if (!document.getElementById('fc-css')) {
                        const l = document.createElement('link');
                        l.id = 'fc-css';
                        l.rel = 'stylesheet';
                        l.href = FC_CSS;
                        document.head.appendChild(l);
                    }
                    if (window.FullCalendar) { cb(); return; }
                    const ex = document.getElementById('fc-js');
                    if (ex) { ex.addEventListener('load', cb, { once:true }); return; }
                    const s = document.createElement('script');
                    s.id = 'fc-js';
                    s.src = FC_JS;
                    s.onload = cb;
                    document.head.appendChild(s);
                };

                // --- HU ünnepnapok számoló ---
                const pad = (n) => String(n).padStart(2, '0');
                const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
                const easter = (Y) => { // Meeus/Jones/Butcher
                    let a = Y % 19;
                    let b = Math.floor(Y/100), c = Y % 100;
                    let d = Math.floor(b/4), e = b % 4;
                    let f = Math.floor((b + 8) / 25);
                    let g = Math.floor((b - f + 1) / 3);
                    let h = (19*a + b - d - g + 15) % 30;
                    let i = Math.floor(c/4), k = c % 4;
                    let l = (32 + 2*e + 2*i - h - k) % 7;
                    let m = Math.floor((a + 11*h + 22*l) / 451);
                    let month = Math.floor((h + l - 7*m + 114) / 31); // 3=Mar,4=Apr
                    let day = ((h + l - 7*m + 114) % 31) + 1;
                    return new Date(Y, month-1, day); // Easter Sunday
                };
                const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate()+n); return x; };

                const huHolidaysForYear = (Y) => {
                    const E  = easter(Y);
                    const GF = addDays(E, -2);      // Nagypéntek
                    const EM = addDays(E,  1);      // Húsvét hétfő
                    const PM = addDays(E, 50);      // Pünkösd hétfő

                    const fixed = [
                        [1, 1,  'Újév'],
                        [3, 15, 'Nemzeti ünnep'],
                        [5, 1,  'A munka ünnepe'],
                        [8, 20, 'Államalapítás'],
                        [10, 23,'1956-os forradalom'],
                        [11, 1, 'Mindenszentek'],
                        [12, 25,'Karácsony'],
                        [12, 26,'Karácsony'],
                    ];

                    const out = fixed.map(([m,d,t]) => ({ date:`${Y}-${pad(m)}-${pad(d)}`, title:t }));
                    out.push({ date: ymd(GF), title: 'Nagypéntek' });
                    out.push({ date: ymd(EM), title: 'Húsvét hétfő' });
                    out.push({ date: ymd(PM), title: 'Pünkösd hétfő' });
                    return out;
                };

                let holidaySet = new Set();
                const computeHolidaysForRange = (start, end) => {
                    // biztos ami biztos: a lefedett évek mindegyikét előállítjuk
                    const years = new Set();
                    for (let y = start.getFullYear(); y <= end.getFullYear(); y++) years.add(y);
                    holidaySet = new Set();
                    years.forEach(y => {
                        huHolidaysForYear(y).forEach(h => holidaySet.add(h.date));
                    });
                };

                ensureFullCalendar(() => {
                    if (typeof FullCalendar === 'undefined') return;

                    const cal = new FullCalendar.Calendar(el, {
                        initialView: 'dayGridMonth',
                        height: 'auto',
                        contentHeight: 320,      // ~ fél magasság
                        firstDay: 1,
                        dayMaxEventRows: true,
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: ''
                        },
                        locale: 'hu',

                        // Munkanapok kiemelése (H–P)
                        businessHours: { daysOfWeek: [1,2,3,4,5] },

                        // Saját események a feedből
                        events: eventsUrl ?? [],

                        // Amikor a nézett dátumtartomány változik, újraszámoljuk az ünnepnapokat
                        datesSet(info) {
                            computeHolidaysForRange(info.start, info.end);
                        },

                        // Napi cella felépítése: hétvége/ünnepnap hátterezés
                        dayCellDidMount(arg) {
                            const d = arg.date;
                            const day = d.getDay();       // 0=Vas, 6=Szom
                            const key = ymd(d);

                            if (day === 0) arg.el.classList.add('fc-sunday-cell');
                            if (day === 6) arg.el.classList.add('fc-saturday-cell');

                            if (holidaySet.has(key)) {
                                arg.el.classList.add('fc-holiday-cell');
                                arg.el.setAttribute('title', 'Ünnepnap');
                            }
                        },

                        eventDidMount(info) {
                            const p = info.event.extendedProps || {};
                            info.el.title = `${info.event.title}
Státusz: ${p.status ?? '-'}
Megjegyzés: ${p.note ?? '-'}`;
                        },
                    });

                    cal.render();
                    el.dataset.mounted = '1';
                    window.__timeEntriesCalendar = cal;
                });
            })"
            wire:ignore
            class="rounded-xl overflow-hidden border border-white/10 bg-black/20"
            style="min-height: 360px"
        >
            <div x-ref="cal" style="height: 340px"></div>
        </div>
    </x-filament::section>

    <style>
        /* FullCalendar “dark” border finomítása */
        .fc-theme-standard .fc-scrollgrid, .fc-theme-standard td, .fc-theme-standard th {
            border-color: rgba(255,255,255,.10);
        }
        .fc .fc-toolbar-title { font-weight: 600; }

        /* Vasárnap */
        .fc-sunday-cell  { background: rgba(239,68,68,.18); }   /* pirosas */
        /* Szombat (pihenőnap) */
        .fc-saturday-cell{ background: rgba(234,179,8,.18); }   /* borostyán */
        /* Ünnepnap */
        .fc-holiday-cell { background: rgba(59,130,246,.20); }  /* kékes */

        /* A kijelölt nap vizuálisan is maradjon erős */
        .fc .fc-daygrid-day.fc-day-today { outline: 2px solid rgba(59,130,246,.35); }
    </style>
</x-filament-widgets::widget>
