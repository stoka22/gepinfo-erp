<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl dark:text-gray-200">Géptörzs</h2></x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4">
            <a href="{{ route('machines.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded">Új gép</a>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Kód</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Név</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Hely</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Eszközök</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($machines as $m)
                            <tr>
                                <td class="px-6 py-3 font-mono">{{ $m->code }}</td>
                                <td class="px-6 py-3">{{ $m->name }}</td>
                                <td class="px-6 py-3">{{ $m->location ?? '—' }}</td>
                                <td class="px-6 py-3">{{ $m->devices_count }}</td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('machines.edit',$m) }}" class="text-blue-600">Szerk.</a>
                                    <form action="{{ route('machines.destroy',$m) }}" method="POST" class="inline" onsubmit="return confirm('Törlöd?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 ml-3">Törlés</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-6 py-6 text-gray-500" colspan="5">Nincs gép.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $machines->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
