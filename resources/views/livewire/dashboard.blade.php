<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    @php
    $isAdmin = auth()->user()->role === 'admin';

    $deviceQuery = \App\Models\Device::query();
    if (!$isAdmin) {
        $deviceQuery->where('user_id', auth()->id());
    }
    $deviceCount = $deviceQuery->count();

    $pulseBase = \App\Models\Pulse::with('device');
    if (!$isAdmin) {
        $pulseBase->whereHas('device', fn($q) => $q->where('user_id', auth()->id()));
    }

    $todayTotal = (clone $pulseBase)
        ->whereDate('sample_time', \Carbon\Carbon::today())
        ->sum('delta');

    $weekTotal = (clone $pulseBase)
        ->whereBetween('sample_time', [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()])
        ->sum('delta');

    $latest = (clone $pulseBase)
        ->latest('sample_time')
        ->limit(20)
        ->get();
    $pending = \App\Models\PendingDevice::orderByDesc('last_seen_at')->limit(20)->get();


@endphp
    
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

           <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Várakozó eszközök') }}
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MAC</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Javasolt név</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FW</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Utolsó jel</th>
                                    <th scope="col" class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($pending as $p)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-gray-900 dark:text-gray-100">{{ $p->mac_address }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $p->proposed_name ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $p->fw_version ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $p->ip ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $p->last_seen_at }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form method="POST" action="{{ route('devices.approve', $p->id) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded bg-green-600 hover:bg-green-700 text-white">
                                                    Jóváhagyás
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            Nincs új eszköz.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    


            {{-- Info kártyák (Breeze stílus) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Eszközök') }}</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $deviceCount }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Mai összes impulzus') }}</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $todayTotal }}</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Heti összes impulzus') }}</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $weekTotal }}</div>
                    </div>
                </div>
            </div>

            {{-- Utolsó minták táblázat --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Utolsó minták') }}</div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-2 pr-4">{{ __('Idő') }}</th>
                                    <th class="py-2 pr-4">{{ __('Eszköz') }}</th>
                                    <th class="py-2 pr-4">{{ __('Kumulált') }}</th>
                                    <th class="py-2 pr-4">{{ __('Delta') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-800 dark:text-gray-100">
                                @forelse($latest as $p)
                                    <tr class="border-b border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4">{{ $p->sample_time }}</td>
                                        <td class="py-2 pr-4">{{ $p->device->name ?? ('#'.$p->device_id) }}</td>
                                        <td class="py-2 pr-4">{{ $p->count }}</td>
                                        <td class="py-2 pr-4">{{ $p->delta }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-gray-500 dark:text-gray-400">
                                            {{ __('Nincs adat még.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
