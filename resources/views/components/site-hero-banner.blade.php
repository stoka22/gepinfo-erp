@php
    $services = \App\Support\CompanyServices::all();
@endphp

{{-- A márka-banner (fotó + Gépinfo logó + szlogenek) és az alatta lévő ikonos
     szolgáltatás-sor — ugyanabban a vizuális stílusban, mint eddig, de az ikon-sor
     mostantól valódi HTML/CSS (nem a képbe sütött pixel), ezért automatikusan minden
     szolgáltatást megjelenít (új szolgáltatás felvételekor sincs teendő itt), és minden
     oldal tetején megjelenik, nem csak a főoldalon. A banner-kép alsó, korábban ide
     festett ikon-/pipa-sorát CSS-es "overflow:hidden" vágja le, hogy ne duplázódjon. --}}
<section class="bg-[#060b16]">
    <div class="relative overflow-hidden" style="aspect-ratio: 2056 / 480;">
        <a href="{{ route('home') }}">
            <img src="{{ asset('images/branding/gepinfo.png') }}"
                 alt="Gépinfo – Ipari automatizálás és gépkorszerűsítés. Hatékonyabb gépek. Biztosabb működés. Nagyobb lehetőségek."
                 class="w-full h-auto block">
        </a>
        {{-- A kép eredeti, pixelbe sütött ikon-sora csak ettől a ponttól (430/765 ≈ 56.2%)
             jobbra kezdődik — ezt a sávot takarjuk el egy a háttérrel közel egyező
             sötétkék folttal, hogy ne látsszon bele a régi, statikus ikon-sor teteje
             az új, valódi HTML ikon-sor fölött. A "Kínai gépek..." felirat (bal oldalon,
             ez alatt az x-érték alatt) így változatlanul látszik. --}}
        <div class="absolute" style="left: 16%; right: 0; top: 89.6%; bottom: 0; background: linear-gradient(135deg, #002036, #012948);"></div>
    </div>

    <div class="border-t border-white/10" style="background: radial-gradient(ellipse at top, #0d1b32 0%, #060b16 60%);">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 py-8 flex flex-col sm:flex-row sm:flex-wrap justify-center gap-3 sm:gap-3.5">
            @foreach ($services as $service)
                <a href="{{ route('szolgaltatasok.show', $service['slug']) }}"
                   title="{{ $service['title'] }}"
                   class="group flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] pl-3 pr-5 py-3 w-full sm:w-auto transition-all duration-200 hover:bg-white/[0.08] hover:border-blue-400/50 sm:hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-500/10">
                    <span class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0 text-white shadow-inner transition-transform duration-200 group-hover:scale-105"
                          style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        <x-service-icon :slug="$service['slug']" class="w-5 h-5" />
                    </span>
                    <span class="sm:max-w-[9rem] text-xs sm:text-[13px] font-bold uppercase tracking-wide text-white leading-tight">
                        {{ $service['title'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 py-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs sm:text-sm font-semibold">
            @foreach (['Szakértelem', 'Megbízhatóság', 'Gyakorlati tapasztalat'] as $trait)
                <span class="inline-flex items-center gap-2 text-white">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="none" stroke="#60a5fa" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <polyline points="8,12.5 11,15.5 16,9" />
                    </svg>
                    {{ $trait }}
                </span>
            @endforeach
            <span class="hidden sm:inline text-white/30">|</span>
            <span style="color:#60a5fa;">IPAR. FEJLŐDÉS. JÖVŐ.</span>
        </div>
    </div>
</section>
