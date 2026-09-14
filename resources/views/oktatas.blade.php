<x-site-layout
    title="Oktatás | Gépinfo"
    description="Oktatási anyagok a Gépinfótól: technológiai táblázatok, szoftverek letöltése és oktató videók a gépkezelés elsajátításához.">

    <section class="py-16 sm:py-20 bg-[#060b16] text-white">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
            <h1 class="text-2xl sm:text-3xl font-extrabold">Oktatási anyagok</h1>
            <p class="mt-3 text-slate-400 max-w-xl mx-auto">
                Technológiai táblázatok, szoftverek és oktató videók, amiket a betanítás során is használunk —
                itt bármikor újra elérhetők.
            </p>
        </div>
    </section>

    <section class="py-16 sm:py-20 bg-slate-50">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900">Fájlok</h2>

            @forelse ($fileGroups as $categoryKey => $items)
                <div class="mt-8">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-blue-600">
                        {{ \App\Models\TrainingMaterial::CATEGORIES[$categoryKey] ?? 'Egyéb' }}
                    </h3>
                    <ul class="mt-3 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white overflow-hidden">
                        @foreach ($items as $item)
                            <li class="flex items-center gap-4 p-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-600/10 flex items-center justify-center text-blue-600 shrink-0">
                                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                        <polyline points="14,2 14,8 20,8" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-slate-900">{{ $item->title }}</div>
                                    @if ($item->description)
                                        <div class="text-sm text-slate-500">{{ $item->description }}</div>
                                    @endif
                                </div>
                                @if ($item->file_size_for_humans)
                                    <div class="text-xs text-slate-400 shrink-0">{{ $item->file_size_for_humans }}</div>
                                @endif
                                <a href="{{ $item->file_url }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition shrink-0">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3v12" /><polyline points="7,10 12,15 17,10" /><path d="M5 21h14" />
                                    </svg>
                                    Letöltés
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="mt-4 text-slate-500">Egyelőre nincs feltöltött fájl.</p>
            @endforelse
        </div>
    </section>

    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900">Oktató videók</h2>

            @if ($videos->isEmpty())
                <p class="mt-4 text-slate-500">Egyelőre nincs feltöltött videó.</p>
            @else
                <p class="mt-2 text-sm text-slate-500">
                    Adatvédelmi okból a videók előnézeti képpel jelennek meg — a YouTube-lejátszó csak
                    kattintásra töltődik be. Részletek a
                    <a href="{{ route('adatvedelem') }}" class="text-blue-600 hover:underline">Süti- és adatkezelési tájékoztatóban</a>.
                </p>

                <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-8">
                    @foreach ($videos as $video)
                        <div>
                            <div class="youtube-facade relative aspect-video rounded-xl overflow-hidden bg-black shadow"
                                 data-embed-url="{{ $video->youtube_embed_url }}"
                                 data-title="{{ $video->title }}">
                                @if ($video->youtube_thumbnail)
                                    <img src="{{ $video->youtube_thumbnail }}" alt="{{ $video->title }}" class="w-full h-full object-cover">
                                @endif
                                <button type="button"
                                        class="youtube-play-btn absolute inset-0 flex items-center justify-center w-full h-full group"
                                        aria-label="Videó lejátszása: {{ $video->title }}">
                                    <span class="w-16 h-16 rounded-full bg-black/60 group-hover:bg-red-600 flex items-center justify-center transition">
                                        <svg viewBox="0 0 24 24" class="w-7 h-7 text-white ml-1" fill="currentColor">
                                            <polygon points="7,4 20,12 7,20" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                            <div class="mt-3 font-semibold text-slate-900">{{ $video->title }}</div>
                            @if ($video->description)
                                <div class="text-sm text-slate-500">{{ $video->description }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <script>
                (function () {
                    function embed(facade) {
                        var url = facade.getAttribute('data-embed-url');
                        var title = facade.getAttribute('data-title');
                        if (!url) return;
                        facade.innerHTML = '<iframe class="w-full h-full" src="' + url +
                            '" title="' + title.replace(/"/g, '&quot;') +
                            '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                    }

                    document.querySelectorAll('.youtube-play-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            embed(btn.closest('.youtube-facade'));
                        });
                    });

                    function embedAllIfConsented() {
                        if (window.gepinfoCookieConsent && window.gepinfoCookieConsent.getConsent() === 'all') {
                            document.querySelectorAll('.youtube-facade').forEach(embed);
                        }
                    }

                    document.addEventListener('DOMContentLoaded', embedAllIfConsented);
                    document.addEventListener('cookie-consent-changed', function (e) {
                        if (e.detail.status === 'all') {
                            document.querySelectorAll('.youtube-facade').forEach(embed);
                        }
                    });
                })();
                </script>
            @endif
        </div>
    </section>

</x-site-layout>
