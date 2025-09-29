<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            {{-- Fejléc --}}
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold tracking-tight">{{ $monthLabel }}</h3>
                <div class="flex gap-2">
                    <x-filament::button icon="heroicon-m-chevron-left" color="gray" wire:click="previousMonth" />
                    <x-filament::button icon="heroicon-m-chevron-right" color="gray" wire:click="nextMonth" />
                </div>
            </div>

            {{-- Napok sora --}}
            <div class="grid grid-cols-7 text-[11px] uppercase tracking-wide text-white/60">
                @foreach (['H','K','Sze','Cs','P','Szo','V'] as $d)
                    <div class="px-2 py-1">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Naptár rács --}}
            <div class="grid grid-cols-7 gap-px rounded-xl overflow-hidden bg-white/10">
                @foreach($this->days as $day)
                    @php
                        $has     = count($day['entries']) > 0;
                        $isToday = $day['dateStr'] === now()->toDateString();

                        $base    = 'relative min-h-[112px] p-2 transition-colors bg-black/20 hover:bg-white/5';
                        $any     = $has ? 'bg-emerald-500/8' : '';
                        $today   = ($isToday && $has) ? 'bg-emerald-600/15 ring-1 ring-emerald-400/30' : '';
                        $dim     = $day['inMonth'] ? '' : 'opacity-50';
                        $sel     = ($this->selectedDate === $day['dateStr']) ? 'ring-2 ring-sky-400/60 bg-sky-500/10' : '';
                    @endphp

                    <div class="{{ $base }} {{ $any }} {{ $today }} {{ $dim }} {{ $sel }}">
                        <div class="flex items-center justify-between text-xs text-white/70">
                            <span>{{ $day['day'] }}</span>
                            @if($has)
                                <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] rounded-full bg-white/10 border border-white/20">
                                    {{ count($day['entries']) }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-1 space-y-1">
                            @foreach(array_slice($day['entries'], 0, 2) as $it)
                                @php
                                    $type = $it['type'];
                                    $cls = match($type) {
                                        'vacation'   => 'bg-amber-500/20 text-amber-100 border-amber-400/30',
                                        'overtime'   => 'bg-sky-500/20 text-sky-100 border-sky-400/30',
                                        'sick_leave' => 'bg-rose-500/20 text-rose-100 border-rose-400/30',
                                        default      => 'bg-white/10 text-white/80 border-white/20',
                                    };
                                    $label = match($type) {
                                        'vacation'   => 'Szabadság',
                                        'overtime'   => 'Túlóra' . ($it['hours'] ? ' · ' . number_format($it['hours'], 2) . ' h' : ''),
                                        'sick_leave' => 'Táppénz',
                                        default      => ucfirst(str_replace('_', ' ', $type)),
                                    };
                                @endphp
                                <div class="px-2 py-1 text-[11px] rounded-md border {{ $cls }} truncate">
                                    {{ $label }} — {{ $it['employee'] }}
                                </div>
                            @endforeach

                            @if($has)
                                <button
                                    type="button"
                                    class="text-[11px] text-sky-300 hover:underline"
                                    wire:click="openDay('{{ $day['dateStr'] }}')">
                                    {{ count($day['entries']) > 2 ? '+' . (count($day['entries']) - 2) . ' további…' : 'Részletek' }}
                                </button>
                            @endif
                        </div>

                        @if($has)
                            <button
                                type="button"
                                class="absolute inset-0"
                                aria-label="Open day"
                                wire:click="openDay('{{ $day['dateStr'] }}')">
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Filament Modal – táblázatos részletek --}}
            <x-filament::modal id="time-entries-day" :visible="$showModal" width="4xl" :slide-over="false">
                <x-slot name="heading">
                    {{ $modalDateLabel }}
                </x-slot>

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
                            @forelse($modalItems as $it)
                                <tr class="border-t border-white/10">
                                    <td class="p-3">{{ $it['employee'] }}</td>
                                    <td class="p-3">
                                        @php
                                            $t = match($it['type']) {
                                                'vacation' => 'Szabadság',
                                                'overtime' => 'Túlóra',
                                                'sick_leave' => 'Táppénz',
                                                default => ucfirst(str_replace('_',' ',$it['type'])),
                                            };
                                        @endphp
                                        {{ $t }}
                                    </td>
                                    <td class="p-3">
                                        {{ $it['hours'] ? number_format($it['hours'], 2) . ' h' : '—' }}
                                    </td>
                                    <td class="p-3">{{ $it['start'] }}</td>
                                    <td class="p-3">{{ $it['end'] ?? '—' }}</td>
                                    <td class="p-3">
                                        @php
                                            $sLbl = ['approved'=>'Jóváhagyva','rejected'=>'Elutasítva','pending'=>'Függőben'][$it['status']] ?? $it['status'];
                                            $sCls = match($it['status']) {
                                                'approved' => 'text-emerald-200',
                                                'rejected' => 'text-rose-200',
                                                default    => 'text-white/70',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-xs bg-white/10 {{ $sCls }}">{{ $sLbl }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-4 text-center text-white/60">Nincs adat erre a napra.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot name="footer">
                    <x-filament::button color="gray" wire:click="closeModal">Bezár</x-filament::button>
                </x-slot>
            </x-filament::modal>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
