@php
    $services = \App\Support\CompanyServices::all();
    $activeSlug = request()->routeIs('szolgaltatasok.show') ? request()->route('slug') : null;
@endphp

{{-- Állandó (nem görgethető) fejléc — logó + egyedi, "fül"-szerű (ferde szélű,
     egymásba csúsztatott) szolgáltatás-menü, üveg-hatású sötét sávon, neon-kék izzó
     ikon-jelvényekkel. A jelenlegi oldal fülje kiemelt, kék-cián gradiens kitöltésű.
     Minden fül alatt mindig látható felirat van (nem csak hover-tooltip), mert érintős
     eszközön nincs hover. A linkek a cél-oldal főcíméhez (#szolgaltatas-cim)
     horgonyoznak, hogy kattintás után a nézet rögtön a címre ugorjon, ne a fotós
     bannerre. A site-layout.blade.php flex app-shell elrendezésében ez és a lábléc fix
     magasságú, csak a köztük lévő <main> görgethető. --}}
<header class="relative shrink-0 z-30" style="background: rgba(6,10,20,0.92); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);">
    <div class="mx-auto max-w-6xl px-3 sm:px-6 pt-2.5 flex items-end gap-3 sm:gap-6">
        <a href="{{ route('home') }}" class="shrink-0 pb-2.5 font-extrabold text-lg tracking-tight text-white hover:opacity-80 transition">
            Gép<span style="color:#38bdf8; text-shadow: 0 0 14px rgba(56,189,248,.65);">info</span>
        </a>

        <nav class="min-w-0 flex-1 overflow-x-auto sm:overflow-visible [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            <div class="flex items-end w-max sm:w-full sm:justify-center mx-auto">
                @foreach ($services as $i => $service)
                    @php $isActive = $activeSlug === $service['slug']; @endphp
                    <a href="{{ route('szolgaltatasok.show', $service['slug']) }}#szolgaltatas-cim"
                       title="{{ $service['title'] }}"
                       class="group relative shrink-0 flex flex-col items-center gap-1 pt-2.5 pb-2 px-4 sm:px-5 transition-all duration-200 {{ $isActive ? 'z-20' : 'hover:z-10' }}"
                       style="margin-left: {{ $i === 0 ? '0' : '-11px' }};
                              clip-path: polygon(11px 0, 100% 0, calc(100% - 11px) 100%, 0 100%);
                              background: {{ $isActive ? 'linear-gradient(160deg, #38bdf8, #2563eb)' : 'rgba(15,23,42,.6)' }};
                              {{ $isActive ? 'box-shadow: 0 -2px 22px rgba(56,189,248,.5);' : '' }}">
                        <span class="flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-full transition-all duration-200 group-hover:scale-110 group-hover:[box-shadow:0_0_16px_rgba(56,189,248,.55)]"
                              style="background: {{ $isActive ? 'rgba(255,255,255,.18)' : 'radial-gradient(circle at 32% 28%, rgba(56,189,248,.20), rgba(10,16,32,.95))' }};
                                     border: 1.5px solid {{ $isActive ? 'rgba(255,255,255,.6)' : 'rgba(56,189,248,.45)' }};">
                            <x-service-icon :slug="$service['slug']" class="w-4.5 h-4.5 sm:w-5 sm:h-5" style="color: {{ $isActive ? '#ffffff' : '#7dd3fc' }}; filter: drop-shadow(0 0 5px rgba(56,189,248,.6));" />
                        </span>
                        <span class="text-[10.5px] sm:text-[11.5px] font-semibold leading-none whitespace-nowrap" style="color: {{ $isActive ? '#ffffff' : '#e2e8f0' }};">
                            {{ $service['short'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>

    {{-- Izzó "alapvonal", amin a fülek ülnek. --}}
    <div class="h-[3px] w-full" style="background: linear-gradient(90deg, transparent, #38bdf8 15%, #818cf8 50%, #38bdf8 85%, transparent); box-shadow: 0 0 12px rgba(56,189,248,.6);"></div>
</header>
