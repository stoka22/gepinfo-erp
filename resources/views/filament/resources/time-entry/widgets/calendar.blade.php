<x-filament-widgets::widget>
    <x-filament::section>
        <div
            x-data="{ open:false, items:[], dateLabel:'', selectedDate:'' }"
            class="space-y-4"
        >
            {{-- Fejléc + típus-kapcsolók --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <h3 class="text-xl font-semibold tracking-tight">
                        {{ $monthLabel }}
                    </h3>

                    {{-- Típus-kapcsolók (Presence/Jelenlét alapból NINCS a $typeFilter-ben) --}}
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <label class="inline-flex items-center gap-1 px-2 py-1 rounded bg-amber-500/10 border border-amber-400/30">
                            <input type="checkbox" class="rounded border-white/20 bg-black/20"
                                   wire:model.live="typeFilter" value="vacation">
                            <span>Szabadság</span>
                        </label>
                        <label class="inline-flex items-center gap-1 px-2 py-1 rounded bg-sky-500/10 border border-sky-400/30">
                            <input type="checkbox" class="rounded border-white/20 bg-black/20"
                                   wire:model.live="typeFilter" value="overtime">
                            <span>Túlóra</span>
                        </label>
                        <label class="inline-flex items-center gap-1 px-2 py-1 rounded bg-rose-500/10 border border-rose-400/30">
                            <input type="checkbox" class="rounded border-white/20 bg-black/20"
                                   wire:model.live="typeFilter" value="sick_leave">
                            <span>Táppénz</span>
                        </label>
                        <label class="inline-flex items-center gap-1 px-2 py-1 rounded bg-white/10 border border-white/20">
                            <input type="checkbox" class="rounded border-white/20 bg-black/20"
                                   wire:model.live="typeFilter" value="presence">
                            <span>Jelenlét</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-2">
                    <x-filament::button icon="heroicon-m-chevron-left" color="gray" wire:click="previousMonth" />
                    <x-filament::button icon="heroicon-m-chevron-right" color="gray" wire:click="nextMonth" />
                </div>
            </div>

            {{-- Hét napjai --}}
            <div class="grid grid-cols-7 text-[11px] uppercase tracking-wide text-white/60">
                @foreach (['H','K','Sze','Cs','P','Szo','V'] as $d)
                    <div class="px-2 py-1">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Naptár rács --}}
            <div class="grid grid-cols-7 gap-px rounded-xl overflow-hidden bg-white/10">
                @foreach($this->days as $day)
                    @php
                        // csak a bekapcsolt típusok számítanak "láthatónak"
                        $filteredEntries = isset($typeFilter)
                            ? array_values(array_filter($day['entries'] ?? [], fn($e) => in_array($e['type'], $typeFilter)))
                            : ($day['entries'] ?? []);

                        $has     = count($filteredEntries) > 0;
                        $isToday = $day['date']->isToday();

                        $base   = 'relative min-h-[112px] p-2 transition-colors bg-black/20 hover:bg-white/5';
                        $today  = $isToday && $has ? 'bg-emerald-600/15 ring-1 ring-emerald-400/30' : '';
                        $dim    = $day['inMonth'] ? '' : 'opacity-50';
                    @endphp

                    <div
                        class="{{ $base }} {{ $today }} {{ $dim }}"
                        x-bind:class="selectedDate === '{{ $day['date']->toDateString() }}' ? 'ring-2 ring-sky-400/60 bg-sky-500/10' : ''"
                    >
                        {{-- nap szám + darabszám badge --}}
                        <div class="flex items-center justify-between text-xs text-white/70">
                            <span>{{ $day['date']->day }}</span>
                            @if($has)
                                <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] rounded-full bg-white/10 border border-white/20">
                                    {{ count($filteredEntries) }}
                                </span>
                            @endif
                        </div>

                        {{-- rövid lista 2 elemig --}}
                        <div class="mt-1 space-y-1">
                            @foreach(array_slice($filteredEntries, 0, 2) as $it)
                                @php
                                    $type = $it['type'];
                                    $cls = match($type) {
                                        'vacation'   => 'bg-amber-500/20 text-amber-100 border-amber-400/30',
                                        'overtime'   => 'bg-sky-500/20 text-sky-100 border-sky-400/30',
                                        'sick_leave' => 'bg-rose-500/20 text-rose-100 border-rose-400/30',
                                        'presence'   => 'bg-white/10 text-white/80 border-white/20',
                                        default      => 'bg-white/10 text-white/80 border-white/20',
                                    };
                                    $label = match($type) {
                                        'vacation'   => 'Szabadság',
                                        'overtime'   => 'Túlóra' . ($it['hours'] ? ' · ' . number_format($it['hours'], 2) . ' h' : ''),
                                        'sick_leave' => 'Táppénz',
                                        'presence'   => ($it['status'] === 'checked_in' ? 'Bejelentkezve' : ($it['status'] === 'checked_out' ? 'Kijelentkezve' : 'Jelenlét')),
                                        default      => ucfirst(str_replace('_', ' ', $type)),
                                    };
                                @endphp

                                <div class="px-2 py-1 text-[11px] rounded-md border {{ $cls }} truncate">
                                    {{ $label }} — {{ $it['employee'] }}
                                </div>
                            @endforeach

                            @if($has)
                                <button
                                    class="text-[11px] text-sky-300 hover:underline"
                                    x-on:click='selectedDate="{{ $day["date"]->toDateString() }}";
                                                open=true;
                                                items=@js($filteredEntries);
                                                dateLabel=@js($day["date"]->translatedFormat("Y. MMM d., l"));'>
                                    {{ count($filteredEntries) > 2 ? '+' . (count($filteredEntries) - 2) . ' további…' : 'Részletek' }}
                                </button>
                            @endif
                        </div>

                        {{-- teljes cella kattintható --}}
                        @if($has)
                            <button class="absolute inset-0" aria-label="Open day"
                                x-on:click='selectedDate="{{ $day["date"]->toDateString() }}";
                                            open=true;
                                            items=@js($filteredEntries);
                                            dateLabel=@js($day["date"]->translatedFormat("Y. MMM d., l"));'>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Modal – táblázatos részletek --}}
            <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/60" @click="open=false"></div>
                <div class="relative w-full max-w-4xl mx-4 rounded-xl border border-white/15 bg-black/80 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-lg font-semibold" x-text="dateLabel"></div>
                        <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" @click="open=false"/>
                    </div>

                    <div class="overflow-auto rounded-lg border border-white/10">
                        <table class="w-full text-sm">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="text-left p-3">Dolgozó</th>
                                    <th class="text-left p-3">Típus</th>
                                    <th class="text-left p-3">Órák</th>
                                    <th class="text-left p-3">Kezdet</th>
                                    <th class="text-left p-3">Vége</th>
                                    <th class="text-left p-3">Státusz</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="it in items" :key="it.id">
                                    <tr class="border-t border-white/10">
                                        <td class="p-3" x-text="it.employee"></td>
                                        <td class="p-3">
                                            <span
                                                x-text="
                                                    it.type === 'vacation'   ? 'Szabadság' :
                                                    it.type === 'overtime'   ? 'Túlóra'    :
                                                    it.type === 'sick_leave' ? 'Táppénz'   :
                                                    it.type === 'presence'   ? (it.status === 'checked_in' ? 'Jelenlét · Bejelentkezve' : (it.status === 'checked_out' ? 'Jelenlét · Kijelentkezve' : 'Jelenlét')) :
                                                    it.type
                                                ">
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span x-show="it.hours" x-text="Number(it.hours).toFixed(2) + ' h'"></span>
                                            <span x-show="!it.hours" class="text-white/40">—</span>
                                        </td>
                                        <td class="p-3" x-text="it.start"></td>
                                        <td class="p-3"><span x-text="it.end ?? '—'"></span></td>
                                        <td class="p-3">
                                            <span
                                                class="px-2 py-0.5 rounded text-xs"
                                                :class="{
                                                    'bg-white/10': true,
                                                    'text-emerald-200': it.status === 'approved' || it.status === 'checked_in',
                                                    'text-rose-200': it.status === 'rejected',
                                                    'text-white/70': it.status === 'pending' || it.status === 'checked_out',
                                                }"
                                                x-text="
                                                    it.status === 'approved'    ? 'Jóváhagyva'    :
                                                    it.status === 'rejected'    ? 'Elutasítva'    :
                                                    it.status === 'pending'     ? 'Függőben'      :
                                                    it.status === 'checked_in'  ? 'Bejelentkezve' :
                                                    it.status === 'checked_out' ? 'Kijelentkezve' :
                                                    it.status
                                                ">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
