<x-site-layout
    :title="$service['title'] . ' | Gépinfo'"
    :description="$service['summary']">

    @push('head')
        <script type="application/ld+json">
            {!! json_encode([
                '@@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Főoldal', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Szolgáltatások', 'item' => route('szolgaltatasok.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $service['title'], 'item' => route('szolgaltatasok.show', $service['slug'])],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
        <script type="application/ld+json">
            {!! json_encode([
                '@@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => $service['title'],
                'description' => $service['summary'],
                'provider' => [
                    '@type' => 'LocalBusiness',
                    'name' => 'Gépinfo (Tóth Gábor Ev.)',
                ],
                'areaServed' => 'HU',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endpush

    <x-slot:hero>
        <section class="bg-[#060b16]">
            <img src="{{ asset('images/services/' . $service['slug'] . '.svg') }}"
                 alt="{{ $service['title'] }}"
                 class="w-full h-auto block">
        </section>
    </x-slot:hero>

    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <nav aria-label="Morzsamenü" class="text-sm text-slate-500">
                <a href="{{ route('home') }}" class="hover:text-blue-600 transition">Főoldal</a>
                <span class="mx-1">/</span>
                <a href="{{ route('szolgaltatasok.index') }}" class="hover:text-blue-600 transition">Szolgáltatások</a>
                <span class="mx-1">/</span>
                <span class="text-slate-700">{{ $service['title'] }}</span>
            </nav>

            <h1 class="mt-4 text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $service['title'] }}</h1>

            <div class="mt-6 text-lg leading-relaxed text-slate-600 space-y-4">
                @foreach (explode("\n\n", $service['description']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            <a href="{{ route('kapcsolat') }}"
               class="mt-10 inline-flex items-center rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-blue-500 transition">
                Kérdésem van, kapcsolatfelvétel
            </a>

            @php $otherServices = array_filter(\App\Support\CompanyServices::all(), fn ($s) => $s['slug'] !== $service['slug']); @endphp
            <div class="mt-16 pt-8 border-t border-slate-200">
                <h2 class="text-lg font-bold text-slate-900">További szolgáltatásaink</h2>
                <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                    @foreach ($otherServices as $other)
                        <li>
                            <a href="{{ route('szolgaltatasok.show', $other['slug']) }}" class="text-blue-600 hover:text-blue-700 hover:underline transition">
                                {{ $other['title'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

</x-site-layout>
