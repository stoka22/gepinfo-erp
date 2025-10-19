<x-filament::page>
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold">Munkafolyamatok & elvárt skillek</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                @foreach($this->workflows as $wf)
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="font-semibold">{{ $wf['name'] }}</div>
                        <div class="text-sm text-white/70">{{ $wf['description'] }}</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach(($wf['skills'] ?? []) as $ws)
                                <span class="px-2 py-1 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-200 text-xs">
                                    {{ $ws['name'] }} · L{{ $ws['pivot']['required_level'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold">Képességmátrix (Dolgozó × Skill)</h2>
            <div class="overflow-auto rounded-xl border border-white/10">
                <table class="min-w-[900px] w-full text-sm">
                    <thead>
                        <tr class="bg-white/5">
                            <th class="text-left p-3 sticky left-0 bg-white/5">Dolgozó</th>
                            @foreach($this->skills as $sk)
                                <th class="p-3 text-left">{{ $sk['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->employees as $emp)
                            <tr class="border-t border-white/10">
                                <td class="p-3 sticky left-0 bg-black/20">{{ $emp['name'] }}</td>
                                @foreach($this->skills as $sk)
                                    @php $lvl = $this->matrix[$emp['id']][$sk['id']] ?? null; @endphp
                                    <td class="p-3">
                                        @if(!is_null($lvl))
                                            <span class="px-2 py-1 rounded-md bg-sky-600/20 border border-sky-500/30 text-sky-200">
                                                L{{ $lvl }}
                                            </span>
                                        @else
                                            <span class="text-white/40">–</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::page>
