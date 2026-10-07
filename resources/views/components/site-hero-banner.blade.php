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

    <div class="border-t border-white/10">
        <div class="mx-auto max-w-6xl px-2 sm:px-4 py-6 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-x-2 gap-y-6">
            @foreach ($services as $service)
                <a href="{{ route('szolgaltatasok.show', $service['slug']) }}"
                   title="{{ $service['title'] }}"
                   class="group flex flex-col items-center text-center gap-2">
                    <span class="flex items-center justify-center w-12 h-12 rounded-full border-2 transition-colors"
                          style="border-color:#2563eb; color:#60a5fa;">
                        <x-service-icon :slug="$service['slug']" class="w-6 h-6 transition-transform group-hover:scale-110" />
                    </span>
                    <span class="text-[11px] sm:text-xs font-bold uppercase tracking-wide text-white leading-tight">
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
