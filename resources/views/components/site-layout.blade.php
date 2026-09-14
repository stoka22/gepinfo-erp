@props([
    'title' => 'Gépinfo – Ipari automatizálás & gépkorszerűsítés',
    'description' => null,
    'ogImage' => null,
    'noindex' => false,
])

@php
    $canonical = url()->current();
    $ogImageUrl = $ogImage ?? asset('images/branding/gepinfo.png');
@endphp

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow' }}">
    <link rel="canonical" href="{{ $canonical }}">

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Gépinfo">
    <meta property="og:locale" content="hu_HU">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:title" content="{{ $title }}">
    @if ($description)
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:image" content="{{ $ogImageUrl }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description)
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    <meta name="twitter:image" content="{{ $ogImageUrl }}">

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    {{-- Helyi vállalkozás strukturált adat (LocalBusiness) — segíti a Google-t
         a cégadatok (elérhetőség, közösségi profilok) értelmezésében. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => 'Gépinfo (Tóth Gábor Ev.)',
            'image' => asset('images/branding/gepinfo-logo.png'),
            'url' => route('home'),
            'telephone' => '+36202883944',
            'email' => 'Gepinfo.Gabor@gmail.com',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Dombóvár',
                'addressCountry' => 'HU',
            ],
            'sameAs' => [
                'https://www.facebook.com/profile.php?id=61594259104337',
                'https://www.youtube.com/channel/UCmPaHL2JPqSaMFAFtgXKYcw',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    @stack('head')
</head>
<body class="bg-white text-slate-800 antialiased font-sans">

    {{-- Ha az oldalnak van hero-képe/bannere, az kerül legfelülre — nincs rá
         épített külön fejléc/navbar, a navigáció a lábléc része (lásd lent). --}}
    {{ $hero ?? '' }}

    {{ $slot }}

    <footer style="background:#000000;" class="text-sm">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 py-8 flex flex-col items-center gap-3 text-center">
            {{-- 1. sor: navigáció + logó (a "Gépinfo" felirat vezet a belépés oldalra) --}}
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2">
                <a href="{{ route('filament.user.auth.login') }}"
                   title="Belépés"
                   style="color:#ffffff;"
                   class="font-extrabold text-xl tracking-tight hover:opacity-80 transition">
                    Gép<span style="color:#60a5fa;">info</span>
                </a>
                <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm font-semibold">
                    <a href="{{ route('home') }}#rolunk" style="color:#e2e8f0;" class="hover:opacity-80 transition">Rólunk</a>
                    <a href="{{ route('szolgaltatasok.index') }}" style="color:#e2e8f0;" class="hover:opacity-80 transition">Szolgáltatások</a>
                    <a href="{{ route('oktatas') }}" style="color:#e2e8f0;" class="hover:opacity-80 transition">Oktatás</a>
                    <a href="{{ route('kapcsolat') }}" style="color:#e2e8f0;" class="hover:opacity-80 transition">Kapcsolat</a>
                </nav>
            </div>

            {{-- 2. sor: szerviz (fogaskerék, elkülönítve elöl) + copyright + facebook + youtube --}}
            <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs" style="color:#94a3b8;">
                <a href="{{ route('jumpcodes.public') }}"
                   title="Szerviz"
                   aria-label="Szerviz"
                   style="color:#ef4444;"
                   class="inline-flex items-center hover:opacity-80 transition">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
                    </svg>
                </a>
                <span>&copy; {{ date('Y') }} Tóth Gábor Ev. — Gépinfo.</span>
                <a href="https://www.facebook.com/profile.php?id=61594259104337"
                   target="_blank" rel="noopener noreferrer"
                   title="Facebook"
                   aria-label="Facebook"
                   style="color:#1877f2;"
                   class="inline-flex items-center hover:opacity-80 transition">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                        <path d="M22 12a10 10 0 1 0-11.5 9.87v-6.99H7.9V12h2.6V9.8c0-2.57 1.53-3.99 3.87-3.99 1.12 0 2.3.2 2.3.2v2.53h-1.3c-1.28 0-1.68.8-1.68 1.62V12h2.86l-.46 2.88h-2.4v6.99A10 10 0 0 0 22 12Z" />
                    </svg>
                </a>
                <a href="https://www.youtube.com/channel/UCmPaHL2JPqSaMFAFtgXKYcw"
                   target="_blank" rel="noopener noreferrer"
                   title="YouTube"
                   aria-label="YouTube"
                   style="color:#ff0000;"
                   class="inline-flex items-center hover:opacity-80 transition">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                        <path d="M23.5 6.7a3.02 3.02 0 0 0-2.12-2.14C19.5 4 12 4 12 4s-7.5 0-9.38.56A3.02 3.02 0 0 0 .5 6.7 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.3 3.02 3.02 0 0 0 2.12 2.14C4.5 20 12 20 12 20s7.5 0 9.38-.56a3.02 3.02 0 0 0 2.12-2.14A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.3ZM9.6 15.6V8.4L15.8 12Z" />
                    </svg>
                </a>
            </div>
        </div>
    </footer>

</body>
</html>
