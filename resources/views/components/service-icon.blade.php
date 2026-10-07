@props(['slug'])

{{-- Ugyanaz az ikon-rajzolat, mint a szolgáltatás saját aloldalának hero-SVG-jében
     (public/images/services/{slug}.svg) — itt kis méretben, önállóan újrahasznosítva,
     hogy a főoldal ikon-sora és az aloldal hero-képe vizuálisan mindig egyezzen. --}}
<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    @switch($slug)
        @case('telepites')
            <rect x="10" y="20" width="28" height="18" rx="2" />
            <line x1="24" y1="4" x2="24" y2="18" />
            <polyline points="17,11 24,18 31,11" />
            @break

        @case('atalakitas')
            <polyline points="8,34 19,23 27,29 40,12" />
            <polyline points="31,12 40,12 40,21" />
            @break

        @case('automatizalas')
            <rect x="14" y="14" width="20" height="20" rx="2" />
            <line x1="18" y1="14" x2="18" y2="8" />
            <line x1="24" y1="14" x2="24" y2="8" />
            <line x1="30" y1="14" x2="30" y2="8" />
            <line x1="18" y1="34" x2="18" y2="40" />
            <line x1="24" y1="34" x2="24" y2="40" />
            <line x1="30" y1="34" x2="30" y2="40" />
            <line x1="14" y1="18" x2="8" y2="18" />
            <line x1="14" y1="24" x2="8" y2="24" />
            <line x1="34" y1="18" x2="40" y2="18" />
            <line x1="34" y1="24" x2="40" y2="24" />
            @break

        @case('plc-hmi')
            <rect x="8" y="10" width="32" height="20" rx="2" />
            <line x1="24" y1="30" x2="24" y2="36" />
            <line x1="16" y1="38" x2="32" y2="38" />
            <line x1="14" y1="17" x2="24" y2="17" />
            <line x1="14" y1="22" x2="20" y2="22" />
            @break

        @case('korszerusites')
            <circle cx="24" cy="24" r="16" />
            <line x1="18" y1="30" x2="30" y2="18" />
            <polyline points="21,18 30,18 30,27" />
            @break

        @case('hibakereses')
            <circle cx="20" cy="20" r="10" />
            <line x1="27" y1="27" x2="38" y2="38" />
            @break

        @case('javitas')
            <polygon points="26,6 14,26 22,26 18,42 34,20 24,20" />
            @break

        @case('weblap-keszites')
            <rect x="6" y="9" width="36" height="26" rx="2" />
            <line x1="6" y1="16" x2="42" y2="16" />
            <circle cx="10.5" cy="12.5" r="1" fill="currentColor" stroke="none" />
            <circle cx="14.5" cy="12.5" r="1" fill="currentColor" stroke="none" />
            <circle cx="18.5" cy="12.5" r="1" fill="currentColor" stroke="none" />
            <polyline points="18,23 13,27 18,31" />
            <polyline points="30,23 35,27 30,31" />
            <line x1="26" y1="21" x2="22" y2="33" />
            @break
    @endswitch
</svg>
