<x-filament-panels::page wire:poll.5s>
    @php
        $devices = $this->getDevices();
        $onlineCount = $devices->filter(fn ($d) => $d->is_online)->count();
        $offlineCount = $devices->count() - $onlineCount;
        $mappedMachineCount = $devices->pluck('machines')->flatten()->pluck('id')->unique()->count();

        $rssiClass = function (?int $rssi): string {
            if ($rssi === null) return '';
            if ($rssi >= -60) return 'good';
            if ($rssi >= -75) return 'fair';
            return 'poor';
        };

        $formatUptime = function (?int $seconds): string {
            if ($seconds === null) return '-';
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds % 86400, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $parts = [];
            if ($days) $parts[] = "{$days}n";
            if ($hours || $days) $parts[] = "{$hours}ó";
            $parts[] = "{$minutes}p";
            return implode(' ', $parts);
        };

        $formatShortAge = function (?\Carbon\Carbon $state): string {
            if (! $state) return '-';
            $seconds = $state->diffInSeconds(now());
            if ($seconds < 60) return "{$seconds}s";
            $minutes = intdiv($seconds, 60);
            if ($minutes < 60) return "{$minutes}m";
            $hours = intdiv($minutes, 60);
            if ($hours < 24) return $hours.':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
            return intdiv($hours, 24).'d';
        };
    @endphp

    <div class="dv-page">
        <div class="dv-toolbar">
            <div class="dv-muted">Eszközök, MAC-címek, API-kulcs állapot, csatorna-gép hozzárendelés és élő állapot.</div>
            <button type="button" class="dv-btn" wire:click="$refresh">Frissítés</button>
        </div>

        <div class="dv-summary-grid">
            <div class="dv-card dv-summary-card">
                <div class="dv-label">Összes eszköz</div>
                <div class="dv-value">{{ $devices->count() }}</div>
            </div>
            <div class="dv-card dv-summary-card">
                <div class="dv-label">Online</div>
                <div class="dv-value dv-ok">{{ $onlineCount }}</div>
            </div>
            <div class="dv-card dv-summary-card">
                <div class="dv-label">Offline</div>
                <div class="dv-value dv-bad">{{ $offlineCount }}</div>
            </div>
            <div class="dv-card dv-summary-card">
                <div class="dv-label">Hozzárendelt gép</div>
                <div class="dv-value">{{ $mappedMachineCount }}</div>
            </div>
        </div>

        <div class="dv-card">
            <div class="dv-table-scroll">
                <table class="dv-table">
                    <thead>
                        <tr>
                            <th>Eszköz</th>
                            <th>ESP32 / Device UID</th>
                            <th>Állapot</th>
                            <th>Utolsó adat</th>
                            <th>Élő állapot</th>
                            <th>Firmware</th>
                            <th>Gépek</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            @php
                                $live = $device->meta['live'] ?? null;
                                $activeCommand = $device->activeCommand;
                                $target = $device->meta['firmware_target_version'] ?? null;
                            @endphp
                            <tr wire:key="device-row-{{ $device->id }}">
                                <td>
                                    <b>{{ $device->name }}</b><br>
                                    <span class="dv-muted">{{ $device->location ?: '-' }}</span>
                                </td>
                                <td>
                                    <b>{{ $device->mac_address }}</b><br>
                                    <span class="dv-muted">{{ $device->platform ?: '-' }}</span><br>
                                    <span class="dv-muted">API: {{ $device->api_key_hash ? 'beállítva' : 'nincs beállítva' }}</span>
                                </td>
                                <td>
                                    <span class="dv-badge {{ $device->is_online ? 'online' : 'offline' }}">
                                        {{ $device->is_online ? 'online' : 'offline' }}
                                    </span>
                                </td>
                                <td>{{ $formatShortAge($device->last_seen_at) }}</td>
                                <td>
                                    @if (! $live && ! $device->ssid)
                                        <span class="dv-muted">Nincs élő adat</span>
                                    @else
                                        <div class="dv-live-cell">
                                            <span class="dv-ssid">{{ $device->ssid ?: '-' }}</span>
                                            <span class="dv-rssi dv-{{ $rssiClass($device->rssi) }}">{{ $device->rssi ?? '-' }} dBm</span>
                                            <span class="dv-muted">IP: {{ $live['ip'] ?? '-' }}</span>
                                            <span class="dv-muted">Uptime: {{ $formatUptime($live['uptime_seconds'] ?? null) }}</span>
                                            <span class="dv-ota-badge {{ ($live['ota_enabled'] ?? false) ? 'on' : 'off' }}">
                                                {{ ($live['ota_enabled'] ?? false) ? 'OTA engedélyezve' : 'OTA letiltva' }}
                                            </span>
                                        </div>
                                    @endif
                                    @if ($activeCommand)
                                        <span class="dv-reboot-badge pending">{{ $activeCommand->cmd }} függőben</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dv-live-cell">
                                        <span class="dv-ssid">{{ $device->fw_version ?: 'Ismeretlen' }}</span>
                                        @if (! $target)
                                            <span class="dv-muted">Nincs cél kijelölve</span>
                                        @elseif ($target === $device->fw_version)
                                            <span class="dv-ok">Naprakész</span>
                                        @else
                                            <span class="dv-warn">Frissítés kiküldve: {{ $target }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @forelse ($device->machines as $machine)
                                        <span class="dv-machine-pill">{{ $machine->name }}</span>
                                    @empty
                                        <span class="dv-muted">— nincs hozzárendelve —</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="dv-actions">
                                        <div class="dv-action-group">
                                            <a href="{{ \App\Filament\Resources\DeviceResource::getUrl('edit', ['record' => $device]) }}"
                                               class="dv-icon-btn"
                                               title="Szerkesztés (WiFi hálózatok, firmware-cél, csatorna-gép hozzárendelés)">
                                                <x-filament::icon icon="heroicon-o-pencil-square" class="dv-icon" />
                                            </a>
                                            <button type="button" class="dv-icon-btn"
                                                    wire:click="reboot({{ $device->id }})" wire:confirm="Biztosan újraindítod?"
                                                    @if ($activeCommand) disabled @endif
                                                    title="Újraindítás (parancsot küld az eszköznek, a következő push-kor hajtja végre)">
                                                <x-filament::icon icon="heroicon-o-arrow-path" class="dv-icon" />
                                            </button>
                                            @if ($activeCommand)
                                                <button type="button" class="dv-icon-btn"
                                                        wire:click="stopCommands({{ $device->id }})" wire:confirm="Leállítod a függőben lévő parancsokat?"
                                                        title="Függőben lévő parancsok leállítása">
                                                    <x-filament::icon icon="heroicon-o-hand-raised" class="dv-icon" />
                                                </button>
                                            @endif
                                        </div>
                                        <div class="dv-action-group dv-action-group-danger">
                                            <button type="button" class="dv-icon-btn dv-icon-danger"
                                                    wire:click="factoryReset({{ $device->id }})" wire:confirm="Biztosan factory reset-eled? Az eszköz újra-enrollmentre fog szorulni."
                                                    title="Factory reset (törli az eszköz NVS-tárolóját, újra-enrollment szükséges utána)">
                                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="dv-icon" />
                                            </button>
                                            <button type="button" class="dv-icon-btn dv-icon-danger"
                                                    wire:click="deleteDevice({{ $device->id }})" wire:confirm="Biztosan törlöd ezt az eszközt?"
                                                    title="Eszköz törlése (végleges, az összes hozzá tartozó adattal együtt)">
                                                <x-filament::icon icon="heroicon-o-trash" class="dv-icon" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="dv-muted">Nincs eszköz.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        /* A .dv-page egy CSS grid a Filament oldal-tartalom (szintén flex/
           grid) BELSEJÉBEN -- grid/flex gyermekeknek alapból "min-width:
           auto" az értékük, ami miatt a bennük lévő táblázat min-width-je
           az EGÉSZ oldalt szélesebbre nyomta a viewportnál (ezért lógott ki,
           és emiatt nem érvényesült a .dv-table-scroll-on beállított
           overflow-x: auto sem -- a görgetés csak akkor működik, ha a szülő
           tényleg korlátozza a szélességet). min-width: 0 mindenhol a
           láncban, hogy a görgetés a táblázat SAJÁT dobozán belül maradjon. */
        .dv-page { display: grid; gap: 16px; min-width: 0; }

        .dv-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .dv-card, .dv-summary-card {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 18px;
            padding: 16px;
            color: #e5e7eb;
            min-width: 0;
        }

        .dv-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .dv-summary-card .dv-label { color: #94a3b8; font-size: 13px; }
        .dv-summary-card .dv-value { font-size: 26px; font-weight: 800; margin-top: 6px; }

        .dv-muted { color: #94a3b8; }
        .dv-ok { color: #22c55e; }
        .dv-bad { color: #fb7185; }
        .dv-warn { color: #facc15; }

        .dv-btn {
            display: inline-flex;
            align-items: center;
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 12px;
            padding: 9px 13px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
        }
        .dv-btn.dv-secondary { background: #334155; }
        .dv-btn.dv-danger { background: #dc2626; }
        .dv-btn.dv-small { padding: 6px 9px; font-size: 12px; }
        .dv-btn[disabled] { opacity: .5; cursor: not-allowed; }

        .dv-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background: #334155;
            border: 0;
            border-radius: 9px;
            cursor: pointer;
        }
        .dv-icon-btn:hover { background: #475569; }
        .dv-icon-btn.dv-icon-danger { background: rgba(220, 38, 38, .2); }
        .dv-icon-btn.dv-icon-danger:hover { background: rgba(220, 38, 38, .35); }
        .dv-icon-btn[disabled] { opacity: .4; cursor: not-allowed; }
        .dv-icon { width: 16px; height: 16px; color: #e5e7eb; }
        .dv-icon-danger .dv-icon { color: #fca5a5; }

        /* Fix magasságú belső görgetés: a táblázat X (vízszintes) ÉS Y
           (függőleges) irányban is a SAJÁT dobozán belül görgethető,
           ahelyett hogy az egész oldal nyúlna a sorok számával -- a fejléc
           "position: sticky"-vel a görgető konténerhez (nem a viewporthoz)
           rögzítve marad, ez a nearest-scrolling-ancestor szabály miatt itt
           pontosan ezt a divet jelenti (overflow-y:auto rajta). */
        .dv-table-scroll { width: 100%; max-height: 65vh; overflow: auto; }

        .dv-table { width: 100%; min-width: 1000px; border-collapse: collapse; table-layout: fixed; }
        .dv-table th, .dv-table td {
            border-bottom: 1px solid #1e293b;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
            color: #e5e7eb;
            overflow-wrap: break-word;
        }
        .dv-table th:last-child, .dv-table td:last-child { width: 90px; }
        .dv-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #0f172a;
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .dv-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 800;
        }
        .dv-badge.online { background: rgba(34, 197, 94, .15); color: #22c55e; }
        .dv-badge.offline { background: rgba(248, 113, 113, .15); color: #fb7185; }

        .dv-live-cell { display: grid; gap: 2px; font-size: 12px; min-width: 150px; }
        .dv-live-cell .dv-ssid { font-weight: 800; color: #e5e7eb; }
        .dv-rssi { font-weight: 800; }
        .dv-rssi.dv-good { color: #22c55e; }
        .dv-rssi.dv-fair { color: #facc15; }
        .dv-rssi.dv-poor { color: #fb7185; }

        .dv-ota-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 800;
        }
        .dv-ota-badge.on { background: rgba(34, 197, 94, .15); color: #22c55e; }
        .dv-ota-badge.off { background: rgba(148, 163, 184, .15); color: #94a3b8; }

        .dv-reboot-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 800;
            margin-top: 4px;
            background: rgba(250, 204, 21, .15);
            color: #facc15;
        }

        .dv-machine-pill {
            display: inline-flex;
            border-radius: 999px;
            padding: 3px 9px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(59, 130, 246, .15);
            color: #93c5fd;
            margin: 2px;
        }

        .dv-actions { display: flex; flex-direction: column; gap: 8px; }
        .dv-action-group { display: grid; grid-template-columns: repeat(2, 30px); gap: 6px; }
        .dv-action-group-danger { padding-top: 8px; border-top: 1px dashed #334155; }
    </style>
</x-filament-panels::page>
