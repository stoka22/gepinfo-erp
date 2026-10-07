{{-- A márka-banner (fotó + Gépinfo logó + szlogenek) — a görgethető tartalom (<main>)
     tetején jelenik meg minden oldalon, a fix fejléc (site-header.blade.php, ott van
     a szolgáltatás-menü) alatt. Görgetéskor ez a sáv kigördül a látható területről,
     a fejléc és a lábléc viszont mindig a helyén marad. A banner-kép alsó, korábban ide
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
             sötétkék folttal, hogy ne látsszon bele a régi, statikus ikon-sor teteje.
             A "Kínai gépek..." felirat (bal oldalon, ez alatt az x-érték alatt) így
             változatlanul látszik. --}}
        <div class="absolute" style="left: 16%; right: 0; top: 89.6%; bottom: 0; background: linear-gradient(135deg, #002036, #012948);"></div>
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
