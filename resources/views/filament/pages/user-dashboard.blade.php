<x-filament-panels::page class="fi-dashboard-page">
    @if ($this->getEmployee())
        <x-filament-widgets::widgets
            :columns="$this->getHeaderWidgetsColumns()"
            :data="$this->getWidgetData()"
            :widgets="$this->getHeaderWidgets()"
        />

        <x-filament::section heading="Jelenléti ív megnyitása">
            {{-- Legördülő hónapválasztó (nem csak az utolsó 3 hónap), mert a korábban importált
                 jelenléti adatok jó része régebbi hónapokra esik – a régi 3 gombos verzió ezeket
                 elérhetetlenné tette. A PDF-ek alapértelmezésben megnyílnak (nem letöltésre
                 kényszerítenek), új böngésző-fülön. --}}
            <div class="flex flex-wrap items-end gap-2">
                <x-filament::input.wrapper class="max-w-xs">
                    <x-filament::input.select onchange="if (this.value) window.open(this.value, '_blank')">
                        @for ($m = 0; $m <= 24; $m++)
                            <option value="{{ route('my-attendance-sheet', ['monthsAgo' => $m]) }}" @selected($m === 0)>
                                {{ \Illuminate\Support\Carbon::now()->subMonthsNoOverflow($m)->translatedFormat('Y. F') }}
                            </option>
                        @endfor
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <x-filament::button tag="a" href="{{ route('my-attendance-sheet', ['monthsAgo' => 0]) }}" target="_blank" icon="heroicon-o-document-text">
                    Aktuális hónap megnyitása
                </x-filament::button>
                <x-filament::button tag="a" href="{{ route('my-attendance-sheet-detailed', ['monthsAgo' => 0]) }}" target="_blank" color="gray" icon="heroicon-o-list-bullet">
                    Aktuális hónap – részletes nézet
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            Ehhez a fiókhoz nincs dolgozói adatlap társítva, ezért a szabadság/túlóra adatok és a jelenléti ív nem elérhetők.
        </x-filament::section>
    @endif
</x-filament-panels::page>
