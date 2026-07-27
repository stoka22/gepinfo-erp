<div class="mx-auto max-w-3xl">
    <x-filament::card class="rounded-2xl border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/60 shadow-sm">
        {{-- Fejléc --}}
        <div class="flex items-center gap-3 px-6 pt-6">
            {{-- Use Tailwind sizing instead of custom CSS --}}
            <x-heroicon-o-key class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
            <h2 class="text-2xl font-semibold tracking-tight">Ugrókód generátor</h2>
        </div>

        {{-- Tartalom --}}
        <div class="px-6 pb-6 pt-4">
            {{-- Form összeállítva a Livewire form-mal --}}
            {{ $this->form }}

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="generate" icon="heroicon-m-sparkles">
                    Generálás
                </x-filament::button>

                <x-filament::button color="gray" wire:click="clear" icon="heroicon-m-x-mark">
                    Űrlap törlése
                </x-filament::button>
            </div>

            {{-- Eredmény szekció --}}
            @if($code)
                <x-filament::section
                    icon="heroicon-o-check-badge"
                    heading="Generált kód"
                    description="V{{ (int) ($data['variant'] ?? 1) }} változat"
                    class="mt-6"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="text-3xl font-bold tracking-wider select-all" id="generated-code">
                            {{ $code }}
                        </div>

                        {{-- Biztonságos JS string beszúrás --}}
                        <x-filament::button
                            icon="heroicon-m-clipboard"
                            color="gray"
                            x-data
                            x-on:click="navigator.clipboard.writeText({{ json_encode($code) }}); $dispatch('notify', { title: 'Kimásolva a vágólapra.' })"
                        >
                            Másolás
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </x-filament::card>
</div>
