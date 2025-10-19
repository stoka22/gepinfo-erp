{{-- resources/views/filament/pages/_jump-code-form.blade.php --}}
<form method="POST" action="{{ route('jump-code-generate') }}">
    @csrf
    <div class="space-y-4">
        <div class="bg-white shadow rounded-lg p-4 dark:bg-gray-800">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="key" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Kulcs</label>
                    <input id="key" name="key" value="{{ old('key', $key ?? '') }}" type="text" autofocus
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Pl.: 63118"/>
                    @error('key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded bg-primary-600 px-4 py-2 text-white">Generál</button>
                </div>
            </div>
        </div>

        @if(isset($code))
            <div class="bg-white shadow rounded-lg p-4">
                <div class="text-2xl font-semibold">{{ $code }}</div>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $code }}')">Másol</button>
            </div>
        @endif

        @if(isset($error))
            <div class="bg-white shadow rounded-lg p-4 text-red-600">{{ $error }}</div>
        @endif
    </div>
</form>
