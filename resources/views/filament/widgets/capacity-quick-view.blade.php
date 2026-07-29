<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kapacitás gyorsnézet</x-slot>
        <x-slot name="description">A legjobban kihasznált gépek (következő 30 nap)</x-slot>

        <div class="space-y-3">
            @forelse($topMachines as $row)
                @php
                    $pct = $row['utilization_pct'];
                    $color = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
                @endphp
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium">{{ $row['machine']->name }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ number_format($pct, 1) }}%</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full {{ $color }}" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nincs aktív gép vagy elérhető adat.</p>
            @endforelse
        </div>

        <div class="mt-4">
            <a href="{{ $analysisUrl }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Teljes kapacitáselemzés megnyitása →
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
