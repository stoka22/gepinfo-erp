# Funkció-változásnapló

Ez a fájl a rendszerben élesített (production-ra kerülő) jelentősebb funkciókat és
üzleti szabály-változásokat rögzíti, dátum szerint, a legújabb felül. Cél: egy helyen
lásd, mi és miért változott, anélkül hogy a git commit-history-t kellene bogarászni.

## 2026-07-28

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
