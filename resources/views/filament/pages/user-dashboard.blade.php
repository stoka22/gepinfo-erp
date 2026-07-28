<x-filament-panels::page class="fi-dashboard-page">
    @if ($this->getEmployee())
        <x-filament-widgets::widgets
            :columns="$this->getHeaderWidgetsColumns()"
            :data="$this->getWidgetData()"
            :widgets="$this->getHeaderWidgets()"
        />

        <x-filament::section heading="Jelenléti ív letöltése">
            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" href="{{ route('my-attendance-sheet', ['monthsAgo' => 0]) }}" icon="heroicon-o-arrow-down-tray">
                    Aktuális hónap
                </x-filament::button>
                <x-filament::button tag="a" href="{{ route('my-attendance-sheet', ['monthsAgo' => 1]) }}" color="gray" icon="heroicon-o-arrow-down-tray">
                    Előző hónap
                </x-filament::button>
                <x-filament::button tag="a" href="{{ route('my-attendance-sheet', ['monthsAgo' => 2]) }}" color="gray" icon="heroicon-o-arrow-down-tray">
                    2 hónappal ezelőtt
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            Ehhez a fiókhoz nincs dolgozói adatlap társítva, ezért a szabadság/túlóra adatok és a jelenléti ív nem elérhetők.
        </x-filament::section>
    @endif
</x-filament-panels::page>
