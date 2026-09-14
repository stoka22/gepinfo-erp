<x-site-layout
    title="Szolgáltatások | Gépinfo"
    description="Gépek telepítése, ipari automatizálás, PLC- és HMI-programozás, korszerűsítés, hibakeresés és javítás — Gépinfo.">

    <section class="py-16 sm:py-20 bg-slate-50">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 text-center">Szolgáltatásaink</h1>
            <p class="mt-3 text-center text-slate-500 max-w-2xl mx-auto">
                A géptelepítéstől a napi üzemeltetésig teljes körű támogatást nyújtunk ipari gépparkja mellett.
            </p>

            <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($services as $service)
                    <a href="{{ route('szolgaltatasok.show', $service['slug']) }}"
                       class="group rounded-2xl bg-white border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition">
                        <img src="{{ asset('images/services/' . $service['slug'] . '.svg') }}"
                             alt="{{ $service['title'] }}"
                             class="w-full h-40 object-cover">
                        <div class="p-6">
                            <div class="font-bold text-slate-900 text-lg leading-snug">{{ $service['title'] }}</div>
                            <p class="mt-2 text-sm text-slate-500">{{ $service['summary'] }}</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-blue-600 group-hover:gap-2 transition-all">
                                Részletek
                                <svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="4" y1="10" x2="16" y2="10" />
                                    <polyline points="10,4 16,10 10,16" />
                                </svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

</x-site-layout>
