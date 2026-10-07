<x-site-layout description="Gépinfo – kínai fémmegmunkáló gépek telepítése, ipari automatizálás, PLC- és HMI-programozás, korszerűsítés, hibakeresés és javítás 25 éves szakmai tapasztalattal.">

    <h1 class="sr-only">Gépinfo – Ipari automatizálás és gépkorszerűsítés Dombóváron, 25 éves tapasztalattal</h1>

    <section id="rolunk" class="py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 text-center">Rólunk</h2>
            <p class="mt-6 text-lg leading-relaxed text-slate-600">
                Kínai fémmegmunkáló gépek — sík- és csőlézer, élhajlító, lemezolló, lézer hegesztő-tisztító
                berendezések — telepítését és üzembe helyezését végezzük. Emellett különböző megmunkálási
                technológiák betanítását vállaljuk irodai és üzemi környezetben, valamint szabásterv-készítés
                oktatását 2D és 3D szinten, expert szinten, 25 éves szakmai tapasztalattal.
            </p>
            <p class="mt-4 text-lg leading-relaxed text-slate-600">
                Munkánk a gép üzembe helyezésével nem ér véget: a betanítástól a napi üzemeltetésen át
                a későbbi hibaelhárításig végigkísérjük ügyfeleinket, hogy a géppark valóban azt a
                hatékonyságot és biztonságot nyújtsa, amiért beruháztak bele.
            </p>

            <ul class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                @foreach (['Szakértelem', 'Megbízhatóság', 'Gyakorlati tapasztalat'] as $point)
                    <li class="flex items-center justify-center gap-2 rounded-xl bg-slate-50 border border-slate-200 py-4 px-3 font-semibold text-slate-700">
                        <svg viewBox="0 0 24 24" class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="4,13 9,18 20,6" />
                        </svg>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-10 text-center">
                <a href="{{ route('szolgaltatasok.index') }}"
                   class="inline-flex items-center rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-blue-500 transition">
                    Szolgáltatásaink megtekintése
                </a>
            </div>
        </div>
    </section>

</x-site-layout>
