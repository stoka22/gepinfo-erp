<x-filament::page>
    <div class="space-y-6">

        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
            <span>Frissítve: {{ $generatedAt }}</span>
            <a href="{{ $devicesUrl }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                Összes eszköz megnyitása &rarr;
            </a>
        </div>

        {{-- Online/offline arány csík --}}
        <div>
            <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                @if ($totalCount > 0)
                    <div class="h-full bg-emerald-500 transition-all" style="width: {{ $onlinePct }}%"></div>
                    <div class="h-full bg-red-500 transition-all" style="width: {{ $offlinePct }}%"></div>
                @else
                    <div class="h-full w-full bg-gray-200 dark:bg-gray-700"></div>
                @endif
            </div>
            <div class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ $onlinePct }}% online</span>
                <span>{{ $offlinePct }}% offline</span>
            </div>
        </div>

        {{-- KPI csempék --}}
        <div class="grid grid-cols-4 gap-3">
            <a href="{{ $devicesUrl }}"
               class="group rounded-xl bg-white p-3 text-center shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-800 dark:ring-white/10">
                <x-heroicon-o-cpu-chip class="mx-auto mb-1 h-5 w-5 text-gray-400 dark:text-gray-500" />
                <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $totalCount }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Összes eszköz</div>
            </a>

            <a href="{{ $devicesUrl }}"
               class="group rounded-xl bg-emerald-50 p-3 text-center shadow-sm ring-1 ring-emerald-600/10 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                <x-heroicon-o-signal class="mx-auto mb-1 h-5 w-5 text-emerald-500" />
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $onlineCount }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Online</div>
            </a>

            <a href="{{ $devicesUrl }}"
               class="group rounded-xl bg-red-50 p-3 text-center shadow-sm ring-1 ring-red-600/10 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-red-500/10 dark:ring-red-500/20">
                <x-heroicon-o-signal-slash class="mx-auto mb-1 h-5 w-5 text-red-500" />
                <div class="text-xl font-bold text-red-600 dark:text-red-400">{{ $offlineCount }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Offline</div>
            </a>

            <a href="{{ $devicesUrl }}"
               class="group rounded-xl bg-amber-50 p-3 text-center shadow-sm ring-1 ring-amber-600/10 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-amber-500/10 dark:ring-amber-500/20">
                <x-heroicon-o-exclamation-triangle class="mx-auto mb-1 h-5 w-5 text-amber-500" />
                <div class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ $weakSignalCount }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Gyenge jel (RSSI &lt; -80)</div>
            </a>
        </div>

        {{-- Offline eszközök --}}
        <x-filament::section icon="heroicon-o-signal-slash" heading="Offline eszközök">
            <x-slot name="description">A legrégebb óta nem jelentkezők legfelül.</x-slot>

            <div x-data="{ search: '' }" class="space-y-3">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Keresés név vagy helyszín szerint..."
                        class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div class="overflow-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="p-3 text-left font-medium text-gray-600 dark:text-gray-300">Eszköz</th>
                                <th class="p-3 text-left font-medium text-gray-600 dark:text-gray-300">Helyszín</th>
                                <th class="p-3 text-left font-medium text-gray-600 dark:text-gray-300">Utolsó jel</th>
                                <th class="p-3 text-left font-medium text-gray-600 dark:text-gray-300">Mennyi ideje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($offlineDevices as $d)
                                @php
                                    $ageDays = $d['last_seen_age'] !== null ? $d['last_seen_age'] / 86400 : null;
                                    $badgeColor = match (true) {
                                        $ageDays === null || $ageDays >= 7 => 'danger',
                                        $ageDays >= 1 => 'warning',
                                        default => 'gray',
                                    };
                                @endphp
                                <tr
                                    x-show="!search || '{{ $d['haystack'] }}'.includes(search.toLowerCase())"
                                    class="border-t border-gray-100 transition hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                                >
                                    <td class="p-3">
                                        <a href="{{ $this->deviceEditUrl($d['id']) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $d['name'] }}
                                        </a>
                                    </td>
                                    <td class="p-3 text-gray-600 dark:text-gray-300">{{ $d['location'] ?? '—' }}</td>
                                    <td class="p-3 text-gray-600 dark:text-gray-300">{{ $d['last_seen_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="p-3">
                                        <x-filament::badge :color="$badgeColor">
                                            {{ $this->formatAge($d['last_seen_age']) }}
                                        </x-filament::badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                        Minden eszköz online.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-filament::section>

        {{-- Firmware megoszlás --}}
        <x-filament::section icon="heroicon-o-cpu-chip" heading="Firmware-verziók megoszlása">
            <x-slot name="description">Ha sok eltérő verzió van használatban, érdemes egységesíteni OTA frissítéssel.</x-slot>

            <div class="space-y-3">
                @forelse ($firmwareDistribution as $i => $row)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">
                                {{ $row['version'] }}
                                @if ($i === 0 && count($firmwareDistribution) > 1)
                                    <x-filament::badge color="info" size="xs">legelterjedtebb</x-filament::badge>
                                @endif
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $row['count'] }} eszköz ({{ $row['pct'] }}%)</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full rounded-full bg-primary-500" style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nincs adat.</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament::page>
