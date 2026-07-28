<x-filament::page>
    <div class="space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-white/5 border border-white/10 text-center">
                <div class="text-2xl font-bold">{{ $totalCount }}</div>
                <div class="text-sm text-white/60">Összes eszköz</div>
            </div>
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-center">
                <div class="text-2xl font-bold text-emerald-400">{{ $onlineCount }}</div>
                <div class="text-sm text-white/60">Online</div>
            </div>
            <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-center">
                <div class="text-2xl font-bold text-red-400">{{ $offlineCount }}</div>
                <div class="text-sm text-white/60">Offline</div>
            </div>
            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-center">
                <div class="text-2xl font-bold text-amber-400">{{ $weakSignalCount }}</div>
                <div class="text-sm text-white/60">Gyenge jel (online, RSSI &lt; -80)</div>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold">Offline eszközök</h2>
            <p class="text-sm text-white/60 mb-2">A legrégebb óta nem jelentkezők legfelül.</p>
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-[600px] w-full text-sm">
                    <thead>
                        <tr class="bg-white/5">
                            <th class="text-left p-3">Eszköz</th>
                            <th class="text-left p-3">Helyszín</th>
                            <th class="text-left p-3">Utolsó jel</th>
                            <th class="text-left p-3">Mennyi ideje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offlineDevices as $d)
                            <tr class="border-t border-white/10">
                                <td class="p-3">{{ $d['name'] }}</td>
                                <td class="p-3">{{ $d['location'] ?? '—' }}</td>
                                <td class="p-3">{{ $d['last_seen_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="p-3 text-red-400">{{ $this->formatAge($d['last_seen_age']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center text-white/60">Minden eszköz online.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold">Firmware-verziók megoszlása</h2>
            <p class="text-sm text-white/60 mb-2">Ha sok eltérő verzió van használatban, érdemes egységesíteni OTA frissítéssel.</p>
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-[400px] w-full text-sm">
                    <thead>
                        <tr class="bg-white/5">
                            <th class="text-left p-3">Verzió</th>
                            <th class="text-right p-3">Eszközök száma</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($firmwareDistribution as $row)
                            <tr class="border-t border-white/10">
                                <td class="p-3">{{ $row['version'] }}</td>
                                <td class="p-3 text-right">{{ $row['count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="p-4 text-center text-white/60">Nincs adat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::page>
