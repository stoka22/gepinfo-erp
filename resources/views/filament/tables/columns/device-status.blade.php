@php
    /** @var \App\Models\Device $rec */
    $rec = $getRecord();
    $cmd = $rec->activeCommand; // lehet null
    $online = $rec->last_seen_at && $rec->last_seen_at->gte(now()->subSeconds(65));

    $map = [
        'ota'           => ['heroicon-o-arrow-up-tray',        '!text-primary-500', 'OTA frissítés folyamatban', true],
        'reboot'        => ['heroicon-o-arrow-path',           '!text-amber-500',   'Újraindítás folyamatban',   true],
        'factory_reset' => ['heroicon-o-exclamation-triangle', '!text-red-500',     'Factory reset folyamatban', true],
    ];

    if ($cmd) {
        [$icon, $color, $title, $spin] = $map[$cmd->cmd] ?? ['heroicon-o-cog-6-tooth','!text-gray-500','Parancs folyamatban', true];
        $stateSig = "cmd-{$cmd->cmd}-{$cmd->status}-{$cmd->updated_at?->timestamp}";
    } else {
        [$icon, $color, $title, $spin] = $online
            ? ['heroicon-o-check-circle', '!text-green-500', 'Online',  true]
            : ['heroicon-o-x-circle',     '!text-red-500',   'Offline', true];

        // online/offline váltásnál is változzon a kulcs
        $stateSig = 'net-'.($online ? 'on' : 'off').'-'.optional($rec->last_seen_at)->timestamp;
    }
@endphp

<div class="w-full flex items-center justify-center"
     wire:key="status-{{ $rec->id }}-{{ $stateSig }}">
    <x-filament::icon
        :icon="$icon"
        class="mx-auto w-5 h-5 {{ $color }} {{ $spin ? 'animate-pulse' : '' }}"
        title="{{ $title }}"
    />
</div>
