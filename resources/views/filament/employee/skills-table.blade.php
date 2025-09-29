{{-- Várjuk a $rows kollekciót --}}
@php
    /** @var \Illuminate\Support\Collection<int,\App\Models\Skill> $rows */
@endphp

<div class="space-y-2">
    <div class="text-sm font-medium">
        Mentett skillek ({{ $rows->count() }})
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">Skill</th>
                    <th class="px-3 py-2 text-left font-medium">Szint</th>
                    <th class="px-3 py-2 text-left font-medium">Vizsga dátuma</th>
                    <th class="px-3 py-2 text-left font-medium">Megjegyzés</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $row->name }}</td>
                        <td class="px-3 py-2">{{ $row->pivot->level }}</td>
                        <td class="px-3 py-2">
                            @php
                                $d = $row->pivot->certified_at
                                    ? \Illuminate\Support\Carbon::parse($row->pivot->certified_at)->format('Y. m. d.')
                                    : null;
                            @endphp
                            {{ $d ?? '—' }}
                        </td>
                        <td class="px-3 py-2">{{ $row->pivot->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-3 text-center text-gray-500 dark:text-gray-400" colspan="4">
                            Nincs mentett skill.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        A táblázat csak megjelenítés. A módosításhoz használd az alábbi szerkesztő mezőket.
    </p>
</div>
