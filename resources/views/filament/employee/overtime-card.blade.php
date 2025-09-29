<x-filament::card>
    <div class="text-sm text-gray-400">Túlóra (óra)</div>
    <div class="mt-2 grid grid-cols-2 gap-4">
        <div>
            <div class="text-xs text-gray-400">Összes éves ({{ $year }})</div>
            <div class="text-3xl font-semibold">{{ $yearly }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400">Aktuális havi</div>
            <div class="text-3xl font-semibold">{{ $monthly }}</div>
        </div>
    </div>
</x-filament::card>
