# Funkció-változásnapló

Ez a fájl a rendszerben élesített (production-ra kerülő) jelentősebb funkciókat és
üzleti szabály-változásokat rögzíti, dátum szerint, a legújabb felül. Cél: egy helyen
lásd, mi és miért változott, anélkül hogy a git commit-history-t kellene bogarászni.

## 2026-07-30 (3)

- **Kártyák (`/admin/cards`) – hiányzó "Új kártya" gomb a lista tetején**: bár a
  létrehozó oldal (`/admin/cards/create`) korábban is működött, a lista oldal fejlécében
  nem jelent meg hozzá gomb (a felhasználó élesben csak a kötegelt kártya-importot látta),
  úgyhogy könnyű volt azt hinni, egyáltalán nincs is ilyen funkció. A `ListCards` oldal
  mostantól explicit módon deklarálja a fejléc-műveletet ("Új kártya hozzáadása" gomb),
  ahelyett hogy a Filament implicit alapértelmezésére hagyatkozna. `assertActionExists`
  Livewire-teszttel megerősítve, hogy a gomb ténylegesen létezik a lapon.

## 2026-07-30 (2)

- **Kártyák (`/admin/cards`) – hozzárendelés ÉS leválasztás a listából**: eddig ezen a
  felületen egy kártya dolgozóhoz rendelését csak a Szerkesztés gombbal, kézzel (állapot
  mezővel) lehetett módosítani, ami könnyen inkonzisztens állapotot okozhatott (pl.
  "Szabad" állapot beállítva, miközben a dolgozó mező még kitöltve maradt) — és leválasztásra
  gyakorlatilag nem volt kényelmes mód. Új két gomb a listasorokban: "Hozzárendelés
  dolgozóhoz" (csak szabad kártyánál) és "Leválasztás a dolgozóról" (csak hozzárendelt
  kártyánál) — mindkettő a már meglévő, tesztelt `CardService`-t használja, így ugyanazok a
  validációk érvényesülnek, mint a Dolgozók listán (pl. már hozzárendelt/blokkolt kártya
  elutasítása). Az "Új kártya" létrehozás funkció valójában már korábban is működött
  (`/admin/cards/create`), csak nem volt eléggé feltűnő — ezt közvetlen teszttel is
  megerősítettem. Új `tests/Feature/Services/CardResourceActionsTest.php` fedi mindhármat.

## 2026-07-30

- **Új admin felület: "Webhook hibák"** (`/admin/terminal-webhook-failures`): a terminál
  webhook (`POST /api/terminal/event`) mostantól minden elutasított/sikertelen kérést
  eltárol egy új `terminal_webhook_failures` táblában — hibakód (érvénytelen token,
  ismeretlen kártya, nincs nyitott belépés, validációs hiba, nincs rendszerfelhasználó),
  HTTP kód, kártya UID, irány, IP cím és a beérkezett kérés nyers törzse (a részletek
  nézetben JSON-ként megjelenítve). Csak admin szerepkörrel látható, az "Eszközök" menü
  alatt. A duplikátum/már-bejelentkezve válaszok szándékosan NEM kerülnek naplózásra
  (nem hibák). Cél: éles terminál-integráció hibakeresésekor ne kelljen a szerver
  log-fájljait bogarászni, hanem a felületen egy helyen látszódjon minden elutasított
  esemény, a pontos okkal és a nyers payload-dal együtt. Új
  `tests/Feature/Api/TerminalWebhookTest.php` tesztek fedik (minden hibaág naplóz,
  a sikeres/duplikált esetek nem, a lista oldal admin jogosultsággal megjelenik).

- **Riasztás a felső navbáron új webhook-hibánál**: bekapcsoltuk a Filament beépített
  adatbázis-értesítési rendszerét az admin panelen (harang ikon a felső navbáron, 30
  másodpercenkénti frissítéssel). Egy új `TerminalWebhookFailureObserver` minden újonnan
  létrejövő `terminal_webhook_failures` rekordnál adatbázis-értesítést küld minden admin
  szerepkörű felhasználónak (hibatípus + kártya UID rövid összefoglalóval, "Megnyitás"
  gombbal a részletekhez). Teszttel lefedve.

## 2026-07-29

- **`CardService::assignByUid()` – kötőjeles/szóközös kártya-UID hozzárendelése hibázott**:
  éles tesztelés közben derült ki, hogy a "Kártya hozzárendelése" művelet (Dolgozók lista)
  minden olyan kártyánál elhasalt ("A megadott kártya (UID) nem létezik a rendszerben"),
  aminek a UID-je kötőjelet, szóközt, kettőspontot vagy pontot tartalmazott (pl.
  "TESZT-WEBHOOK-0001") — a szolgáltatás a bevitt UID-t normalizálta (ezeket a karaktereket
  eltávolítva) keresés előtt, de a `cards.uid` oszlopban tárolt érték normalizálatlan
  maradt, így sosem volt egyezés. Mostantól a keresés mindkét oldalon normalizálva
  hasonlít, PHP-oldali összevetéssel (`CardService::findByNormalizedUid()`). A terminál
  webhook saját kártya-keresését ez NEM érinti (az pontos, nem-normalizált egyezést vár,
  és ez már korábban is helyesen működött). Reprodukálva és javítva lokálisan, új
  `tests/Feature/Services/CardServiceTest.php` teszttel lefedve.

- **Új `docs/terminal-webhook-test-plan.pdf` – élesben futtatható tesztterv a webhookhoz**:
  lépésről lépésre leírt, valós tokent tartalmazó teszt-forgatókönyv (dedikált törölhető
  teszt cég/dolgozó/kártya létrehozása, check-in, duplikált check-in, check-out, majd a
  belépés `event_id`-jának újraküldése kilépés UTÁN, végül takarítás). A tervet lokálisan
  végig is futtattam a `php artisan docs:terminal-webhook-test-plan-pdf` paranccsal generált
  PDF pontosságának ellenőrzésére, és menet közben egy valós hibát talált: a `time_entries.
  company_id` mező kötelező (NOT NULL), ezért egy teszt dolgozóhoz mindenképp kell egy
  (akár dedikált, törölhető) cég is — céghez nem rendelt dolgozóval a check-in `500`-zal
  elhasalt volna. A tesztterv ezt a lépést már helyesen tartalmazza. A forrás
  (`docs/terminal-webhook-test-plan.md` + blade sablon) verziókövetve van, a valós tokent
  tartalmazó PDF a `.gitignore`-ban kizárva.

- **`docs/terminal-webhook-api.pdf` – frissítve a legfrissebb API-viselkedésre**: a PDF
  újragenerálva (`php artisan docs:terminal-webhook-pdf`), a valós tokennel. Tartalmazza a
  `location` mezőt (bejelentkezési helyszín), az UTC/időzóna-eltolás automatikus magyar
  helyi időre konvertálását, és a kilépés utáni idempotencia-javítást (`event_id` alapú
  duplikátum-felismerés bejelentkezés UTÁN történő kilépés esetén is működik). A forrás
  (`docs/terminal-webhook-api.md` és a blade sablon) már korábban is naprakész volt ezekkel
  a változtatásokkal, csak a legenerált PDF maradt el mögöttük.

- **Vezérlőpult – teljes tartalommal feltöltve (korábban üres volt)**: a felhasználó kérésére
  a vezérlőpultra bekerült egy KPI-csík (`StatsOverview`) 6 csempével: Jelenlévő most,
  Távollévő ma, Felülvizsgálandó (needs_review-s bejegyzés), Alacsony készlet, Offline
  eszköz, Csúszásveszélyes rendelés — mindegyik a releváns oldalra mutat kattintásra. A
  "Felülvizsgálandó"/"Alacsony készlet"/"Offline eszköz" logikát egy új, megosztott
  `OperationalAlerts` szolgáltatás adja (ugyanezt használja a napi digest e-mail is,
  kód-duplikáció nélkül). A meglévő "Műszak szerinti jelenlét (ma)" és "Távolléten lévő
  dolgozók (ma)" táblák a KPI-csík alá kerültek rendezve (eddig rendezetlenül, véletlenszerű
  sorrendben jelentek meg más widgetek közt). Új "Kapacitás gyorsnézet" widget mutatja a
  3 legjobban kihasznált gépet és egy linket a teljes kapacitáselemzéshez. A befejezetlen,
  mindig 0-t mutató "EmployeeMetrics" widget törölve lett. A KPI-csík Jelenlévő/Távollévő
  számai ugyanazt a cégcsoport-szűrést kapták, mint a lenti részletes táblák, hogy a két
  szám sose térjen el egymástól (kivéve teljes admin nézetben, ahol nincs szűrés).

- **`/app/time-entries` – hiányzó óra:perc a Kezdet/Vége oszlopban**: a Jelenlét (bejelentkezés/
  kilépés) típusú bejegyzéseknél a "Kezdet"/"Vége" oszlop eddig mindig csak a dátumot mutatta,
  az óra:perc sosem jelent meg (a Jelenlét típus alapból rejtve van a lista szűrőjében, ezért
  ez korábban nem tűnt fel). Mostantól Jelenlét típusnál a dátum mellett a tényleges be-/
  kilépési idő is látszik (a kilépésnél a nyers, kerekítés előtti időpont); más típusoknál
  (szabadság, túlóra stb.) változatlanul csak a dátum jelenik meg, mivel ott nincs értelmezhető
  napszak.
- **`AdminUserSeeder` javítás – az "admin@gepinfo.hu" fiók sosem kapott admin jogot**: a seeder
  eddig csak létrehozta ezt a fiókot (email/név/jelszó), de sosem állította be sem a
  `users.role` oszlopot, sem a Spatie 'admin' szerepkört — így ez a fiók a gyakorlatban semmilyen
  admin jogosultságot nem kapott (emiatt hiányzott pl. a `/app/time-entries` menüpont, és a
  Dolgozók lista is üresen jelent meg neki, mivel több helyen is ELTÉRŐ admin-ellenőrzés él
  párhuzamosan: van ami a `users.role` oszlopot, van ami a Spatie szerepkört nézi). A seeder
  mostantól mindkettőt beállítja, meglévő és újonnan létrehozott fióknál is (idempotens). A
  helyi adatbázisban a meglévő fiókot is közvetlenül javítottam.

## 2026-07-28

- **Munkanapló tábla – oszlopok elrejthetők, "Idő" formátum javítva**: minden oszlop
  elrejthető/megjeleníthető (oszlop-választó ikon), a Helyiség/Belépési pont/Kilépési
  pont oszlop alapértelmezetten rejtve van (kevésbé zsúfolt nézet). Az "Idő" oszlop
  mostantól mindig óra:perc (pl. "3:57") formátumban jelenik meg — korábban az Excel
  export nyers nap-törtrész értéke (pl. "0.16458333333333") jelent meg formázatlanul.
  Ugyanez a javítás az importálásnál is érvényesül (`WorkLogsImport::formatIdo()`), így
  az új importok is tiszta formátumban kerülnek be.

- **XLS import – nagy/összetett Excel "mentés weblapként" exportok megbízható beolvasása**:
  három, egymást átfedő ok miatt hasalt el a "Failed to load ... as a DOM Document" hiba
  nagy, több dolgozós exporteknél: (1) a `<style>` blokk régi CSS-elrejtő trükkje
  (`<!--table {...}-->`), (2) Microsoft "downlevel-revealed" feltételes jelölők
  (`<![if ...]>`/`<![endif]>`) — mindkettő néhány sor után teljesen leállította a
  feldolgozást, hiba nélkül, adatvesztést okozva; (3) a `DOMDocument::loadHTML()` egy
  ártalmatlan HTML-figyelmeztetésnél (pl. duplikált `id`) is PHP warningot dob, amit Laravel
  kivétellé alakít, a PhpSpreadsheet pedig bármilyen kivételt végzetes hibaként kezel, még ha
  a dokumentum valójában hibátlanul felépült. Mindhármat kezeli az `App\Support\SpreadsheetEncoding`
  — egy 54 000+ soros, 34 MB-os valós export fájllal végponttól végpontig tesztelve (55 sorból
  54 305-re javítva).
- **XLS import – üres/fejléc-nélküli-adat lap kezelése**: ha valaki egy csak fejlécet
  tartalmazó (adat nélküli) lapot tölt fel — pl. tévedésből az Excel "mentés weblapként"
  export "keret" .xls fájlját a tényleges adatot tartalmazó .htm helyett —, a
  `RowIterator(2)` hívás "Start row (2) is beyond highest row (1)" hibával elhasalt.
  Mostantól ilyenkor egyszerűen nincs importálható sor, nem crash.
- **`work-logs:import` CLI parancs**: nagy (a webes feltöltés `upload_max_filesize`/memória/
  időkorlátait meghaladó) munkanapló exportokhoz, SSH-ról futtatható. Ugyanazt a
  `WorkLogsImport` logikát használja, mint a webes varázsló (dry-run összesítő, automatikus
  névegyeztetés); a nem azonosítható neveket dolgozó nélkül importálja, ezeket utólag a
  Munkaidő napló listában, a meglévő "Összekapcsolás dolgozóval" tömeges művelettel lehet
  hozzárendelni (vagy előbb létrehozni a hiányzó dolgozói adatlapot). Valós, 16 000+ soros
  bináris XLS export fájllal tesztelve.
- **`work-logs:import` – memóriakeret és egyszeri fájlbeolvasás**: a parancs magának emeli a
  memóriakeretet (1024M) induláskor, hogy ne a CLI alapértelmezett (gyakran 128M) korlátjába
  ütközzön nagy fájloknál. Emellett a `WorkLogsImport` API kiegészült a `unmatchedNamesFromRows()`
  / `resolveParsedRows()` / `importResolvedRows()` metódusokkal, hogy az összesítés, az
  egyeztetés és a mentés ugyanazt a már beolvasott sor-tömböt használja újra a fájl
  háromszori újraolvasása/feldolgozása helyett.

- **Munkanapló import – "fájl nem található" javítás + ellenőrző lépés**: a varázsló 2.
  lépése ("Dolgozó párosítás") hibásan jelezte, hogy a fájl nem található — a
  lépések között a FileUpload állapota még nem a végleges, lemezre mentett elérési
  út, hanem egy Livewire ideiglenes feltöltési objektum, amit a kód eddig csak
  string-ként próbált kezelni. Mostantól mindkét alakot (ideiglenes fájl-objektum és
  végleges tárolt útvonal) helyesen felismeri. Emellett új, harmadik "Ellenőrzés"
  lépés jelenik meg importálás előtt: összesítést mutat (hány sor lesz dolgozóhoz
  rendelve / hány marad dolgozó nélkül), a dolgozó nélkül maradó sorokat kiemelve —
  a tényleges import csak ennek megtekintése/jóváhagyása után indul. Valós
  Livewire/Filament varázsló-teszttel (fájlfeltöltés → dinamikus mezőgenerálás →
  kézi hozzárendelés → import) végponttól végpontig ellenőrizve.

- **Munkanapló import – dolgozó-párosítás varázslóval**: az XLS import mostantól két
  lépésben történik: (1) fájl feltöltése, (2) azokhoz a nevekhez, amikhez nem található
  automatikus (kis-/nagybetűtől független, pontos) névegyezés egy dolgozóval, egy
  legördülő választó jelenik meg soronként, ahol kiválasztható a helyes dolgozó (vagy
  üresen hagyható, ha később kerül hozzárendelésre a listában). Korábban ezek a sorok
  csendben, dolgozó nélkül kerültek be. `App\Imports\WorkLogsImport` felbontva
  `parseRows()` / `unmatchedNames()` / `import()` metódusokra.

- **Munkanapló (WorkLog) import javítás – hétvégi/ünnepi/távollét sorok kihagyása**: éles
  tesztelés után kiderült, hogy azok a sorok, amiknek van neve, de nincs tényleges
  be-/kilépési időpontja (hétvége, ünnep, távollét napok), feleslegesen bekerültek a
  munkanaplóba. Az import mostantól kihagyja azokat a sorokat, ahol sem a kezdés, sem a
  végzés időpont nem olvasható ki. A korábbi hibás importból bekerült üres sorok
  eltávolítására lásd a docs/CHANGELOG.md-hez tartozó SSH/tinker útmutatót (a
  felhasználóval megosztva, nem verziókövetett adat-visszaállítási lépés).

- **Munkanapló (WorkLog) import javítás – rövidebb sorok kezelése**: az előző kódolási
  javítás után a "fake xls" HTML export importja tovább jutott, de egy `Undefined array
  key 6` hibával elhasalt olyan soroknál, ahol kevesebb `<td>` cella szerepelt, mint a
  fejlécben (pl. hiányzó kilépési pont/vég/idő). A sor-cellák listáját mostantól a LAP
  (nem a sor) legmagasabb oszlopáig kényszerítjük, és minden mezőolvasás `isset()`
  védelemmel történik — a hiányzó mezők `null`/üres értéket kapnak összeomlás helyett.
  Valós hibát reprodukáló szintetikus fájllal tesztelve.

- **XLS import javítás – "fake xls" HTML export kódolási hiba**: a `PhpOffice\PhpSpreadsheet\Reader\Exception:
  Failed to load file ... as a DOM Document` hiba (éles környezetben, WorkLog import útján
  jelentkezett) azért történt, mert sok magyar beléptető/időfigyelő rendszer "xls" exportja
  valójában egy HTML-táblázat .xls kiterjesztéssel, Windows-1250 kódolásban — a
  PhpSpreadsheet HTML-olvasójának belső, UTF-8-at feltételező reguláris kifejezése emiatt
  némán null-t adott vissza, amit a könyvtár "sérült fájl" hibaként jelentett, félrevezetve.
  Új `App\Support\SpreadsheetEncoding` segédosztály normalizálja a fájlt (a saját charset
  deklarációjából vagy Windows-1250 feltételezéssel UTF-8-ra konvertálva) MINDEN xls-import
  belépési pontnál (napi jelenlét import, munkanapló import, kártya import, session import)
  betöltés előtt. Valós, hibát okozó fájlformátummal reprodukálva és tesztelve.

- **Deploy pipeline – csomag-manifeszt javítás (tyúk-tojás hiba)**: a korábbi
  `package:discover || true` önmagában nem tudta helyrehozni a megosztott
  `bootstrap/cache/packages.php`/`services.php` fájlt, mert a HIBÁS manifeszt betöltése
  már a bootstrap fázisban elhasal (`Class "Laravel\Breeze\BreezeServiceProvider" not
  found`, mivel a Breeze csak `require-dev`-ben van, éles `--no-dev` telepítésnél nincs
  jelen) — méghozzá minden artisan-hívásnál, BELEÉRTVE magát a `package:discover`-t is,
  ami épp ezt lenne hivatott javítani. Ez `php artisan tinker`-rel manuálisan kiderült;
  a futó FPM workerek OPcache miatt addig nem érezték meg, de egy újraindításnál a teljes
  oldalt levitte volna. Végleges javítás: a deploy script mostantól a manifeszt-fájlokat
  proaktívan törli (`rm -f`) minden artisan-hívás ELŐTT, így a keret mindig tiszta lappal,
  a ténylegesen telepített csomagokból építi újra.

- **Jelenléti ív – utólagos javítás jelölése**: a nap sorszáma mellett `*` jelzi, ha egy
  bejegyzést utólag (a rögzítés után) valaki manuálisan javított, vagy egy felülvizsgálandó
  (auto-kiléptetett/hiányos) bejegyzést jóváhagytak. Lábjegyzet magyarázza a jelölést.
- **Túlóra hibahatárok**: 2 perces korai távozási tolerancia és 10 perces túlóra-türelmi idő
  a napi 8:30-as szabály körül (mindkettő küszöb, nem levonás — a hibahatáron túl a teljes
  eltérés számít).
- **Napi több be-/kilépés helyes kezelése**: a túlóra-elszámolás és a jelenléti ív mostantól
  egy nap ÖSSZES jelenlét-szakaszát (pl. ebédszünettel megszakított munkanap) együttesen veszi
  figyelembe a 8:30-as szabály alkalmazásakor, nem szakaszonként külön-külön — ez korábban napi
  több bejelentkezés esetén téves (duplán büntető), illetve adatvesztő (csak egy szakasz jelent
  meg a nyomtatott íven) eredményt adott.
- **Jelenléti ív – távollét jellege**: a szabadság/táppénz/igazolatlan távollét/túlóra-terhére
  napok mostantól fel vannak tüntetve az íven (korábban üresen maradtak), jóváhagyásra váró
  állapot jelzésével együtt.
- **Jelenléti ív fejléc – havi túlóra-egyenleg javítás**: a fejléc havi/éves túlóra-összege
  mostantól élőben, a napi sorokkal megegyező logikával számol (korábban egy csak részlegesen
  feltöltött, "elszámolt" oszlopból számolt, ami régi importált adatoknál gyakorlatilag mindig
  0-t/változatlan értéket mutatott). A napok táblázat alján összesítő sor is megjelenik.
- **Beléptető terminál webhook – helyszín (location)**: a `POST /api/terminal/event` mostantól
  fogadja és tárolja a szolgáltató által küldött `location` mezőt (bejelentkezési helyszín, több
  telephely megkülönböztetésére). Lásd [`terminal-webhook-api.md`](./terminal-webhook-api.md).
- **Beléptető terminál webhook – időzóna javítás**: ha a szolgáltató explicit UTC-eltolással
  (pl. `Z`) küldi az időbélyeget, a rendszer mostantól helyesen magyar helyi időre konvertálja
  (korábban nyersen, konverzió nélkül tárolta volna, ami eltolt check-in/check-out időt okozott
  volna).
- **Beléptető terminál webhook – idempotencia javítás**: a kilépés eseménye eddig felülírta a
  belépés `event_id`-jét a note mezőben, így egy utólag megismételt belépés-esemény már nem lett
  volna felismerhető duplikátumként (kilépés UTÁN újraküldött azonos belépés-esemény plusz,
  felesleges bejegyzést hozott volna létre). Mostantól a két esemény azonosítója egymás mellett
  megmarad, mindkét irányban helyesen működik a duplikáció-szűrés. Valós curl-teszttel igazolva.
- Proaktív napi digest (needs-review / alacsony készlet / offline eszközök figyelmeztetés).
- Gyártási kapacitáselemzés: gépkihasználtság, csúszásveszélyes rendelési tételek, EDD-sorrendes
  gyártandó termékek lista becsült határidővel, recept nélküli tételek kimutatása.
- Változás-napló (audit log) a kritikus modellekre (TimeEntry, VacationBalance, OvertimeBalance,
  Employee, User) — csak olvasható admin felület.
- Eszközflotta health dashboard (online/offline eszközök, firmware-eloszlás).
- Dolgozói önkiszolgáló felület (`/app`): saját szabadság/túlóra adatok, jelenléti ív letöltés.
- `/app` felhasználói panel jogosultság-kezelésének javítása (a korábbi túl szigorú, majd
  túl megengedő verzió helyett célzott, erőforrásonkénti jogosultság-ellenőrzés).

## 2026-07-27

- Napi jelenléti import (XLS): automatikus szabadság/túlóra/igazolatlan-távollét besorolás,
  munkakezdés fél órás felfelé kerekítéssel, 8:30 utáni idő túlórának számít.
- Nyomtatható jelenléti ív (PDF): ünnepnapok jelölve, aláírás mező minden sorhoz, fejlécben
  szabadság/túlóra összesítő, checkbox alapú hónapválasztás (nem naptár).
- Felülvizsgálandó (needs_review) bejegyzések mindig legfelül jelennek meg a listákban.
- Filament táblák app-szerte: sor-műveletek ikon+tooltip formátumra egységesítve (popup/dropdown
  helyett), a menürendszer és elnevezések átdolgozva.
