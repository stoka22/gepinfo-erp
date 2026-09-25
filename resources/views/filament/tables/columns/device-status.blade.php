@php
    /** @var \App\Models\Device $rec */
    $rec = $getRecord();
    $cmd = $rec->activeCommand; // lehet null
    $online = $rec->last_seen_at && $rec->last_seen_at->gte(now()->subSeconds(65));

    // Inline style-lal színezve, NEM Tailwind-osztállyal: a Filament admin
    // panel lefordított CSS-e (vendor/filament/filament/dist/theme.css)
    // csak a saját palettája ténylegesen használt osztályait tartalmazza
    // (pl. text-danger-500, text-gray-500, text-primary-500) -- egy
    // tetszőleges "!text-green-500"/"!text-amber-500" osztálynak ebben a
    // fájlban sosem volt CSS-szabálya, ezért nem is látszott színesnek.
    $map = [
        'ota'           => ['heroicon-o-arrow-up-tray',        '#3b82f6', 'OTA frissítés folyamatban', true],
        'reboot'        => ['heroicon-o-arrow-path',           '#f59e0b', 'Újraindítás folyamatban',   true],
        'factory_reset' => ['heroicon-o-exclamation-triangle', '#ef4444', 'Factory reset folyamatban', true],
    ];

    if ($cmd) {
        [$icon, $color, $title, $spin] = $map[$cmd->cmd] ?? ['heroicon-o-cog-6-tooth', '#6b7280', 'Parancs folyamatban', true];
        $stateSig = "cmd-{$cmd->cmd}-{$cmd->status}-{$cmd->updated_at?->timestamp}";
    } else {
        [$icon, $color, $title, $spin] = $online
            ? ['heroicon-o-check-circle', '#22c55e', 'Online',  true]
            : ['heroicon-o-x-circle',     '#ef4444', 'Offline', true];

        // online/offline váltásnál is változzon a kulcs
        $stateSig = 'net-'.($online ? 'on' : 'off').'-'.optional($rec->last_seen_at)->timestamp;
    }
@endphp

<div class="w-full flex items-center justify-center"
     wire:key="status-{{ $rec->id }}-{{ $stateSig }}">
    <x-filament::icon
        :icon="$icon"
        style="color: {{ $color }}"
        class="mx-auto w-5 h-5 {{ $spin ? 'animate-pulse' : '' }}"
        title="{{ $title }}"
    />
</div>
