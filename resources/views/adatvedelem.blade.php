<x-site-layout
    title="Süti- és adatkezelési tájékoztató | Gépinfo"
    description="Tájékoztató a gepinfo.hu honlapon használt sütikről, a látogatottság-mérésről és az érintetti jogokról."
    :noindex="false">

    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Süti- és adatkezelési tájékoztató</h1>
            <p class="mt-2 text-sm text-slate-500">Utolsó frissítés: {{ now()->isoFormat('YYYY. MMMM D.') }}</p>

            <div class="mt-8 space-y-8 text-slate-700 leading-relaxed">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">1. Az adatkezelő</h2>
                    <p class="mt-2">
                        Tóth Gábor Ev. (Gépinfo) — székhely: Dombóvár — telefon:
                        <a href="tel:+36202883944" class="text-blue-600 hover:underline">+36 20 288 3944</a> —
                        e-mail: <a href="mailto:Gepinfo.Gabor@gmail.com" class="text-blue-600 hover:underline">Gepinfo.Gabor@gmail.com</a>.
                    </p>
                </div>

                <div>
                    <h2 class="text-lg font-bold text-slate-900">2. Milyen sütiket (cookie-kat) használunk?</h2>

                    <h3 class="mt-4 font-semibold text-slate-900">2.1 Feltétlenül szükséges sütik</h3>
                    <p class="mt-1">
                        Ezek nélkül az oldal alapvető funkciói (pl. bejelentkezés a munkatársi/admin felületre)
                        nem működnének, ezért ezekhez nem kérünk külön hozzájárulást — jogalapjuk a szolgáltatás
                        nyújtásához fűződő jogos érdek.
                    </p>
                    <ul class="mt-3 space-y-1 list-disc list-inside">
                        <li><code class="text-sm bg-slate-100 px-1 rounded">laravel_session</code> — bejelentkezési állapot, munkamenet-azonosító. Kb. 2 óra inaktivitás után lejár.</li>
                        <li><code class="text-sm bg-slate-100 px-1 rounded">XSRF-TOKEN</code> — az űrlapok visszaélés (CSRF-támadás) elleni védelmére. Ugyanaddig érvényes, mint a munkamenet.</li>
                    </ul>

                    <h3 class="mt-4 font-semibold text-slate-900">2.2 A süti-elfogadási döntés tárolása</h3>
                    <p class="mt-1">
                        Amikor a lap alján megjelenő sávban választ (pl. "Mind elfogadom"), ezt a döntést nem
                        sütiben, hanem a böngészője helyi tárolójában (<code class="text-sm bg-slate-100 px-1 rounded">localStorage</code>)
                        rögzítjük. Ez az adat nem kerül a szerverünkre, és böngészőnként/eszközönként külön tárolódik.
                    </p>

                    <h3 class="mt-4 font-semibold text-slate-900">2.3 Harmadik féltől származó tartalom: YouTube</h3>
                    <p class="mt-1">
                        Az <a href="{{ route('oktatas') }}" class="text-blue-600 hover:underline">Oktatás</a> oldalon
                        oktató videókat mutatunk be, amelyek a YouTube-on vannak tárolva. A videó előnézeti képe
                        magától betölt, de a lejátszó (és így a Google esetleges sütijei) csak akkor aktiválódik,
                        ha Ön rákattint a lejátszásra — ezt megelőzően nem kerül kapcsolatba a Google szervereivel
                        a videó miatt. A lejátszást az adatvédelem-barátabb <code class="text-sm bg-slate-100 px-1 rounded">youtube-nocookie.com</code>
                        domainen keresztül indítjuk. A Google adatkezeléséről a
                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Google adatvédelmi szabályzatában</a> tájékozódhat.
                    </p>

                    <h3 class="mt-4 font-semibold text-slate-900">2.4 Amit NEM használunk</h3>
                    <p class="mt-1">
                        Nincs a honlapon Google Analytics, Facebook Pixel vagy más hirdetési/marketing célú
                        követő kód, és nem adunk el, nem továbbítunk adatot marketing célra harmadik félnek.
                    </p>
                </div>

                <div>
                    <h2 class="text-lg font-bold text-slate-900">3. Saját üzemeltetésű látogatottság-mérés</h2>
                    <p class="mt-2">
                        A honlap forgalmának megismeréséhez (pl. melyik oldalt hányan nézik meg) egy saját,
                        süti nélküli mérést használunk. Minden oldalletöltésnél eltároljuk a meglátogatott
                        oldal címét, az Ön böngészőjének típusát (user agent), a hivatkozó oldal címét, az
                        időpontot, valamint az IP-címéből egy vissza nem fejthető, egyirányú hash-értéket
                        (SHA-256) — a nyers IP-cím soha nem kerül tárolásra.
                    </p>
                    <p class="mt-2">
                        Ezt az adatot kizárólag összesített forgalmi statisztika készítésére használjuk, nem
                        alkalmas egyéni profilalkotásra vagy Önnek szóló, személyre szabott hirdetésre. A
                        jogalap a honlap üzemeltetőjének jogos érdeke (GDPR 6. cikk (1) bekezdés f) pont). A
                        rekordokat legfeljebb 12 hónapig őrizzük meg, utána automatikusan töröljük.
                    </p>
                </div>

                <div>
                    <h2 class="text-lg font-bold text-slate-900">4. Az Ön jogai</h2>
                    <p class="mt-2">
                        A rád vonatkozó adatok kezelésével kapcsolatban jogosult tájékoztatást kérni, kérheti
                        az adatok helyesbítését, törlését vagy kezelésének korlátozását, valamint tiltakozhat
                        az adatkezelés ellen. Ezekkel a jogaival a fenti elérhetőségeinken élhet. Amennyiben
                        úgy ítéli meg, hogy adatkezelésünk jogsértő, panasszal élhet a Nemzeti Adatvédelmi és
                        Információszabadság Hatóságnál (NAIH, székhely: 1055 Budapest, Falk Miksa utca 9-11.,
                        honlap: <a href="https://naih.hu" target="_blank" rel="noopener" class="text-blue-600 hover:underline">naih.hu</a>),
                        vagy bírósághoz fordulhat.
                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-500">
                    Ez a tájékoztató a honlap tényleges technikai működése alapján készült. Amennyiben a
                    vállalkozás adatkezelése a jövőben bővül (pl. hírlevél, online fizetés, hirdetési
                    követés), a tájékoztatót és a szükséges jogi hátteret érdemes ügyvéddel/adatvédelmi
                    szakértővel felülvizsgáltatni.
                </div>
            </div>
        </div>
    </section>

</x-site-layout>
