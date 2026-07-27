<x-filament::page>
    <form method="POST" action="{{ url()->current() }}" class="mx-auto max-w-3xl">
        @csrf

        <x-filament::card class="rounded-2xl border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/60 shadow-sm">
            {{-- Fejléc --}}
            <div class="flex items-center gap-3 px-6 pt-6">
                <x-heroicon-o-key class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                <h2 class="text-2xl font-semibold tracking-tight">Ugrókód generátor</h2>
            </div>

            {{-- Tartalom --}}
            <div class="px-6 pb-6 pt-4">
                {{-- Input mezők --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="key" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Kulcs</label>
                        <input id="key" name="key" value="{{ old('key', session('key', $key ?? '')) }}" type="text" autofocus
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Pl.: 63118"/>
                        @error('key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-end">
                        <x-filament::button type="submit" icon="heroicon-m-sparkles">
                            Generálás :
                        </x-filament::button>
                    </div>
                </div>

                {{-- Eredmény szekció --}}
                @if(session('code') || isset($code))
                    <x-filament::section
                        icon="heroicon-o-check-badge"
                        heading="Generált kód"
                        description="V{{ (int) (session('variant', $data['variant'] ?? $variant ?? 1)) }} változat"
                        class="mt-6"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div class="text-3xl font-bold tracking-wider select-all" id="generated-code">
                                {{ session('code', $code ?? '') }}
                            </div>

                            <x-filament::button
                                icon="heroicon-m-clipboard"
                                color="gray"
                                x-data
                                x-on:click="navigator.clipboard.writeText({{ json_encode(session('code', $code ?? '')) }}); $dispatch('notify', { title: 'Kimásolva a vágólapra.' })"
                            >
                                Másolás
                            </x-filament::button>
                        </div>
                    </x-filament::section>
                @endif

                @if(session('error') || isset($error))
                    <div class="mt-4">
                        <x-filament::alert type="danger">
                            {{ session('error', $error ?? 'Hiba történt') }}
                        </x-filament::alert>
                    </div>
                @endif
            </div>
        </x-filament::card>
    </form>
</x-filament::page>
