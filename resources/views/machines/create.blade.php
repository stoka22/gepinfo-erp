<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl dark:text-gray-200">Új gép</h2></x-slot>

    <div class="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
            <form method="POST" action="{{ route('machines.store') }}">
                @csrf
                <div>
                    <label class="block text-sm mb-1">Kód</label>
                    <input name="code" class="w-full rounded border-gray-300 dark:bg-gray-800" required value="{{ old('code') }}">
                    @error('code')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-sm mb-1">Név</label>
                    <input name="name" class="w-full rounded border-gray-300 dark:bg-gray-800" required value="{{ old('name') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1">Hely</label>
                    <input name="location" class="w-full rounded border-gray-300 dark:bg-gray-800" value="{{ old('location') }}">
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Gyártó</label>
                        <input name="vendor" class="w-full rounded border-gray-300 dark:bg-gray-800" value="{{ old('vendor') }}">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Típus</label>
                        <input name="model" class="w-full rounded border-gray-300 dark:bg-gray-800" value="{{ old('model') }}">
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Gyári szám</label>
                        <input name="serial" class="w-full rounded border-gray-300 dark:bg-gray-800" value="{{ old('serial') }}">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Üzembe helyezés</label>
                        <input type="date" name="commissioned_at" class="w-full rounded border-gray-300 dark:bg-gray-800" value="{{ old('commissioned_at') }}">
                    </div>
                </div>
                <div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="active" value="1" class="rounded border-gray-300" checked>
                        <span>Aktív</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm mb-1">Megjegyzés</label>
                    <textarea name="notes" rows="3" class="w-full rounded border-gray-300 dark:bg-gray-800">{{ old('notes') }}</textarea>
                </div>

                <div class="mt-4">
                    <button class="px-4 py-2 bg-indigo-600 text-white rounded">Mentés</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
