@php
    $services = \App\Support\CompanyServices::all();
@endphp

{{-- Állandó (nem görgethető) fejléc — logó + kompakt, csak ikonos szolgáltatás-menü,
     egyedi tooltippel. Szándékosan alacsony (h-16), hogy minél kevesebb helyet vegyen
     el az állandóan látható területből. A site-layout.blade.php flex app-shell
     elrendezésében ez és a lábléc fix magasságú, csak a köztük lévő <main> görgethető. --}}
<header class="relative shrink-0 bg-[#060b16] border-b border-white/10 z-30">
    <div class="mx-auto max-w-6xl px-3 sm:px-6 h-16 flex items-center gap-3 sm:gap-5">
        <a href="{{ route('home') }}" class="shrink-0 font-extrabold text-lg tracking-tight text-white hover:opacity-80 transition">
            Gép<span style="color:#60a5fa;">info</span>
        </a>

        <nav class="min-w-0 flex-1 overflow-x-auto sm:overflow-visible [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            <div class="flex items-center gap-2 sm:gap-2.5 w-max sm:w-full sm:justify-center mx-auto">
                @foreach ($services as $service)
                    <a href="{{ route('szolgaltatasok.show', $service['slug']) }}"
                       aria-label="{{ $service['title'] }}"
                       class="group relative shrink-0 flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-xl text-white transition-transform duration-150 hover:-translate-y-0.5"
                       style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        <x-service-icon :slug="$service['slug']" class="w-4.5 h-4.5 sm:w-5 sm:h-5" />

                        {{-- Egyedi tooltip (nem a böngésző natív title-ja — az lassú és
                             csúnya), csak asztali (hover-képes) eszközön jelenik meg. --}}
                        <span class="pointer-events-none absolute left-1/2 top-full z-40 mt-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-[#0b1830] px-2.5 py-1.5 text-[11px] font-semibold text-white opacity-0 shadow-lg ring-1 ring-white/10 transition-opacity duration-150 group-hover:opacity-100 sm:block">
                            {{ $service['title'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</header>
