<x-filament::card>
    <div class="text-sm text-gray-400">Keret / Felhasznált / Kivehető ({{ $year }})</div>
    <div class="mt-2 grid grid-cols-3 gap-4">
        <div>
            <div class="text-xs text-gray-400">Keret</div>
            <div class="text-3xl font-semibold">{{ $entitled }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400">Felhasznált</div>
            <div class="text-3xl font-semibold">{{ $used }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400">Kivehető</div>
            <div class="text-3xl font-semibold">{{ $available }}</div>
        </div>
    </div>
</x-filament::card>
