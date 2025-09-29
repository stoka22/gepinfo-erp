<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            {{-- Fejléc + navi --}}
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold tracking-tight">{{ $monthLabel }}</h3>
                <div class="flex gap-2">
                    <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="previousMonth" />
                    <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="nextMonth" />
                </div>
            </div>

            {{-- Jelmagyarázat --}}
            <div class="flex items-center gap-4 text-xs text-white/70">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded bg-amber-500/40 border border-amber-400/40"></span>
                    Szabadság
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded bg-rose-500/40 border border-rose-400/40"></span>
                    Táppénz
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded bg-white/20 border border-white/20"></span>
                    Egyéb / nincs adat
                </div>
            </div>

            {{-- Mátrix --}}
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5">
                        <tr>
                            <th class="sticky left-0 z-10 bg-white/5 px-3 py-2 text-left w-60">Dolgozó</th>
                            @foreach($this->days as $d)
                                <th class="px-2 py-2 w-8 text-center">{{ $d['day'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->rows as $row)
                            <tr class="border-t border-white/10">
                                <td class="sticky left-0 z-10 bg-black/30 backdrop-blur px-3 py-2 font-medium">
                                    {{ $row['name'] }}
                                </td>
                                @foreach($this->days as $d)
                                    @php
                                        $cell = $row['cells'][$d['date']] ?? null;
                                        $bg = '';
                                        if ($cell) {
                                            $bg = match($cell['type']) {
                                                'vacation'   => 'bg-amber-500/40 border-amber-400/40',
                                                'sick_leave' => 'bg-rose-500/40 border-rose-400/40',
                                                default      => 'bg-white/10 border-white/20',
                                            };
                                            if (($cell['status'] ?? null) === 'pending') {
                                                $bg .= ' ring-1 ring-white/10';
                                            }
                                        }
                                    @endphp
                                    <td class="px-0.5 py-0.5">
                                        <button
                                            type="button"
                                            class="w-8 h-6 rounded border {{ $cell ? $bg : 'bg-black/10 border-white/10 hover:bg-white/5' }}"
                                            @if($cell)
                                                wire:click="openCell({{ $row['id'] }}, '{{ $d['date'] }}')"
                                                title="Részletek"
                                            @endif
                                        ></button>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 1 + count($this->days) }}" class="p-6 text-center text-white/60">
                                    Nincs megjeleníthető adat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Modal: cella részletek --}}
            <x-filament::modal id="absence-cell-modal" :visible="$showModal" width="4xl">
                <x-slot name="heading">
                    {{ $employeeName }} — {{ $cellDateLabel }}
                </x-slot>

                <div class="overflow-auto rounded-lg border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5">
                            <tr>
                                <th class="text-left p-3">Típus</th>
                                <th class="text-left p-3">Státusz</th>
                                <th class="text-left p-3">Órák</th>
                                <th class="text-left p-3">Kezdet</th>
                                <th class="text-left p-3">Vége</th>
                                <th class="text-left p-3">Megjegyzés</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cellItems as $it)
                                @php
                                    $typeLabel = match($it['type']) {
                                        'vacation'   => 'Szabadság',
                                        'sick_leave' => 'Táppénz',
                                        'overtime'   => 'Túlóra',
                                        default      => ucfirst(str_replace('_',' ',$it['type'])),
                                    };
                                    $statusLabel = match($it['status']) {
                                        'approved' => 'Jóváhagyva',
                                        'pending'  => 'Függőben',
                                        'rejected' => 'Elutasítva',
                                        default    => $it['status'],
                                    };
                                @endphp
                                <tr class="border-t border-white/10">
                                    <td class="p-3">{{ $typeLabel }}</td>
                                    <td class="p-3">{{ $statusLabel }}</td>
                                    <td class="p-3">{{ $it['hours'] ? number_format($it['hours'],2).' h' : '—' }}</td>
                                    <td class="p-3">{{ $it['start'] }}</td>
                                    <td class="p-3">{{ $it['end'] ?? '—' }}</td>
                                    <td class="p-3">{{ $it['note'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-4 text-center text-white/60">Nincs adat.</td></tr>
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
