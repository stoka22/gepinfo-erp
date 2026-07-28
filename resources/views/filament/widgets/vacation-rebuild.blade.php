<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Szabadságkeret karbantartás</x-slot>
        <x-slot name="description">
            SSH nélkül futtatható/ellenőrizhető. Minden deploy automatikusan újraszámolja a jelenlegi évre.
        </x-slot>

        <div class="space-y-2 mb-4">
            @forelse($this->getYearsSummary() as $row)
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">{{ $row['year'] }}</span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $row['employee_count'] }} dolgozó &middot; frissítve: {{ \Illuminate\Support\Carbon::parse($row['last_updated'])->format('Y-m-d H:i') }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Még nincs szabadságkeret-adat.</p>
            @endforelse
        </div>

        {{ $this->rebuildVacationAction }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
