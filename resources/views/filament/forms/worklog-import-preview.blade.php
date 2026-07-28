<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="px-2 py-1 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-300">
            {{ $okCount }} sor dolgozóhoz rendelve
        </span>
        @if(count($problemRows))
            <span class="px-2 py-1 rounded-lg bg-red-600/20 border border-red-500/30 text-red-300">
                {{ count($problemRows) }} sor dolgozó nélkül
            </span>
        @endif
        <span class="text-gray-500 dark:text-gray-400">Összesen {{ $total }} sor kerül importálásra.</span>
    </div>

    @if(count($problemRows))
        <div class="overflow-auto rounded-xl border border-red-500/30 max-h-64">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-red-500/10">
                        <th class="text-left p-2">Név</th>
                        <th class="text-left p-2">Munkakör</th>
                        <th class="text-left p-2">Kezdés</th>
                        <th class="text-left p-2">Vége</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($problemRows as $row)
                        <tr class="border-t border-red-500/20 bg-red-500/5">
                            <td class="p-2 font-medium">{{ $row['nev'] }}</td>
                            <td class="p-2">{{ $row['munkakor'] }}</td>
                            <td class="p-2">{{ $row['kezdes'] }}</td>
                            <td class="p-2">{{ $row['vege'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Ezek a sorok dolgozó nélkül kerülnek importálásra — később a listában, a
            "Összekapcsolás dolgozóval" tömeges művelettel rendelhetők hozzá.
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Minden sorhoz sikerült dolgozót azonosítani.</p>
    @endif
</div>
