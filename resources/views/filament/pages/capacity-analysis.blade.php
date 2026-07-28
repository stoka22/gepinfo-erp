<x-filament::page>
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold">Gyártandó termékek</h2>
            <p class="text-sm text-white/60 mb-2">Nyílt (nem teljesített) rendelési tételek termékenként összesítve, kért határidő (EDD) szerint sorba rendezve. Az ütemezett határidő a receptek és a gépek jelenlegi terhelése alapján becsült befejezés.</p>
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-[900px] w-full text-sm">
                    <thead>
                        <tr class="bg-white/5">
                            <th class="text-left p-3">Termék</th>
                            <th class="text-right p-3">Össz. darabszám</th>
                            <th class="text-left p-3">Kért határidő</th>
                            <th class="text-left p-3">Referencia gép</th>
                            <th class="text-left p-3">Ütemezett határidő</th>
                            <th class="text-center p-3">Állapot</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productionQueue as $row)
                            @php
                                $late = $row['requested_due'] && $row['scheduled_finish'] && $row['scheduled_finish']->gt($row['requested_due']);
                            @endphp
                            <tr class="border-t border-white/10 {{ $late ? 'bg-red-500/10' : '' }}">
                                <td class="p-3">{{ $row['item_name'] }}</td>
                                <td class="p-3 text-right">{{ number_format($row['remaining_qty'], 0) }}</td>
                                <td class="p-3">{{ $row['requested_due']?->format('Y-m-d') ?? '—' }}</td>
                                <td class="p-3">{{ $row['reference_machine']?->name ?? '—' }}</td>
                                <td class="p-3">{{ $row['scheduled_finish']?->format('Y-m-d') ?? '—' }}</td>
                                <td class="p-3 text-center">
                                    @if(! $row['has_recipe'])
                                        <span class="px-2 py-1 rounded-lg bg-gray-600/20 border border-gray-500/30 text-gray-300 text-xs">Nincs recept</span>
                                    @elseif($late)
                                        <span class="px-2 py-1 rounded-lg bg-red-600/20 border border-red-500/30 text-red-300 text-xs">Csúszás</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-300 text-xs">Rendben</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-4 text-center text-white/60">Nincs nyílt rendelési tétel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(count($missingRecipeItems))
            <div>
                <h2 class="text-xl font-bold">Recept nélküli tételek</h2>
                <p class="text-sm text-white/60 mb-2">Ezekhez a nyíltan rendelt termékekhez még nincs rögzítve munkalépés (recept) — ütemezett határidő nem számítható, amíg fel nem veszed a Tételek → Munkalépések fülön.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($missingRecipeItems as $item)
                        <span class="px-2 py-1 rounded-lg bg-amber-600/20 border border-amber-500/30 text-amber-300 text-xs">{{ $item->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <h2 class="text-xl font-bold">Gépek kihasználtsága (következő 30 nap)</h2>
            <p class="text-sm text-white/60 mb-2">A lista teteje a jelenlegi szűk keresztmetszet (legmagasabb kihasználtság).</p>
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-[700px] w-full text-sm">
                    <thead>
                        <tr class="bg-white/5">
                            <th class="text-left p-3">Gép</th>
                            <th class="text-right p-3">Elérhető óra</th>
                            <th class="text-right p-3">Foglalt óra</th>
                            <th class="text-right p-3">Kihasználtság</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($machineUtilization as $row)
                            @php
                                $pct = $row['utilization_pct'];
                                $color = $pct >= 90 ? 'text-red-400' : ($pct >= 70 ? 'text-amber-400' : 'text-emerald-400');
                            @endphp
                            <tr class="border-t border-white/10">
                                <td class="p-3">{{ $row['machine']->name }}</td>
                                <td class="p-3 text-right">{{ number_format($row['available_hours'], 1) }}</td>
                                <td class="p-3 text-right">{{ number_format($row['used_hours'], 1) }}</td>
                                <td class="p-3 text-right font-semibold {{ $color }}">{{ number_format($pct, 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center text-white/60">Nincs aktív gép.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold">Csúszásveszélyes rendelési tételek</h2>
            <p class="text-sm text-white/60 mb-2">Nyílt (nem teljesített) tételek, ahol a recept alapján becsült hátralévő munka meghaladja a határidőig elérhető gépidőt.</p>
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-[900px] w-full text-sm">
                    <thead>
                        <tr class="bg-white/5">
                            <th class="text-left p-3">Rendelés</th>
                            <th class="text-left p-3">Tétel</th>
                            <th class="text-right p-3">Hátralévő menny.</th>
                            <th class="text-right p-3">Szükséges óra</th>
                            <th class="text-right p-3">Elérhető óra (határidőig)</th>
                            <th class="text-right p-3">Napok hátra</th>
                            <th class="text-center p-3">Kockázat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($atRiskItems as $row)
                            <tr class="border-t border-white/10 {{ $row['at_risk'] ? 'bg-red-500/10' : '' }}">
                                <td class="p-3">{{ $row['order_item']->order?->order_no ?? '—' }}</td>
                                <td class="p-3">{{ $row['order_item']->item_name_cache }}</td>
                                <td class="p-3 text-right">{{ number_format($row['remaining_qty'], 0) }}</td>
                                <td class="p-3 text-right">{{ number_format($row['needed_hours'], 1) }}</td>
                                <td class="p-3 text-right">{{ $row['available_hours_until_due'] !== null ? number_format($row['available_hours_until_due'], 1) : '—' }}</td>
                                <td class="p-3 text-right">{{ $row['days_left'] }}</td>
                                <td class="p-3 text-center">
                                    @if($row['at_risk'])
                                        <span class="px-2 py-1 rounded-lg bg-red-600/20 border border-red-500/30 text-red-300 text-xs">Csúszásveszély</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-300 text-xs">Rendben</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-4 text-center text-white/60">Nincs nyílt, határidős rendelési tétel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::page>
