# Archívum

Ez a mappa nem futó, nem autoloadolt kód – korábbi `.bak`/`copy` fájlok és
egy elhagyott service-verzió lett ide áthelyezve törlés helyett, hogy a
git history megmaradjon.

## Miért kellett kiszedni az `app/` alól

Több fájl ugyanazt az osztálynevet és namespace-t deklarálta, mint egy élő,
használt fájl. Amíg az `app/` alatt voltak, ez classmap-ütközést
okozhatott (`composer dump-autoload -o` / `--classmap-authoritative` esetén
nem determinisztikus, hogy melyik definíció "nyer"):

- `app/Http/Controllers/Api/DevicePulseController copy.bak` –
  `App\Http\Controllers\Api\DevicePulseController` duplikátuma.
- `app/Http/JumpCodeController2.php.bak` –
  `App\Http\Controllers\JumpCodeController` duplikátuma.
- `app/Services/JumpCodeGeneratorV4.php` – ténylegesen a
  `class JumpCodeGeneratorV2` nevet deklarálta (fájlnév és osztálynév nem
  egyezett), így ütközött a valódi `JumpCodeGeneratorV2.php`-vel. Emellett
  sehol nem volt rá hivatkozás a kódban – holt kód volt.

## Ami nem ütközési kockázat, csak holt kód

- `app/Filament/User.bak/*` – más namespace-ben van
  (`App\Filament\User\...`), de az `UserPanelProvider` a
  `Filament/Resources` (nem `Filament/User/Resources`) mappából
  discovery-zik resource-okat, tehát ezek sosem töltődtek be.
- `bootstrap/providers.php.bak` – az app `bootstrap/app.php`-ban
  `->withProviders([...])` explicit tömbbel regisztrálja a providereket,
  a `bootstrap/providers.php` fájlt a keretrendszer itt nem olvassa.
- `routes/web copy.bak` – nincs rá `require`/`include` sehol, a
  `routes/web.php` van bekötve a `bootstrap/app.php`-ban.

Ha valamelyikre mégis szükség lenne, itt megvan – csak vissza kell mozgatni
az eredeti helyére és ellenőrizni, hogy nem ütközik-e semmivel.

## Elhagyott, párhuzamos gyártástervező-alrendszer (2026-07-27)

A vizuális Gantt-tábla (SchedulerBoard, React+Zustand frontend) ténylegesen
a `ProductionTask`/`ProductionSplit` modelleket és a
`Http/Controllers/Scheduler/TaskController`-t használja – ez élesben marad.

Emellett létezett egy MÁSODIK, teljesen független, sosem bekötött
implementáció ugyanerre a problémára, amit ide archiváltunk:

- `app/Models/Task.php`, `TaskDependency.php`, `PlanSegment.php` (utóbbi
  0 bájtos üres fájl volt)
- `app/Http/Controllers/PlanningController.php`,
  `Scheduler/PlanSegmentController.php` (hiányzó importtal azonnal fatal
  errorral elszállt volna, ha valaha meghívják), `Scheduler/ShiftController.php`
  (a `shift_patterns` tábla nem létező `resource_id`/`days_mask` oszlopait
  feltételezte – a valós kapcsolat a `resource_shift_assignments` táblán
  keresztül megy, ld. `ResourceShiftAssignment` modell)
- `app/Services/Scheduling/BuildTasksFromItemWorkSteps.php`,
  `OverlapValidator.php`, `DependencyValidator.php`, `CapacityValidator.php`
  (ez utóbbi a `MachineCalendar` táblára épült, aminek soha nem volt admin
  felülete, tehát nem lehetett feltölteni – bekötve minden feladat-létrehozást
  elutasított volna)
- `app/Filament/Resources/TaskDependencyResource.php` – élő admin felület
  volt, de a `Task` táblát soha semmi nem töltötte fel, így a
  előd/utódfeladat választó mezői mindig üresek voltak
- `app/Policies/TaskDependencyPolicy.php`
- `database/seeders/TaskQuickSeed.php`, `TaskDependencyDemoSeeder.php`
- `tests/Feature/Scheduling/{Capacity,Overlap,Dependency}ValidatorTest.php`

Egyik résznek sem volt élő route-ja vagy elérhető adatforrása. A
`WindowPolicy` (műszakablak + géptiltás ellenőrzés) NEM került ide, mert
azt a `ResourceShiftAssignment`/`ShiftPattern` élő admin felülete
ténylegesen használhatóvá teszi – ezt beépítettük a `TaskController`
store/storeSplit/move/resize metódusaiba.

## Régi eszköz-számláló (device/pulse) kontraktus lecserélve (2026-09-24)

A gepinfo darabszámláló (pulse-counter) rendszer teljes újratervezése
(lásd `C:\Users\TothGabor\.claude\plans\whimsical-seeking-flame.md`, a
felhasználó gépén) a testvér "Energy" projekt kontraktusát vette alapul.
Élő hardver soha nem függött a régi `/api/device/hello|pulse` végpontoktól
(az egyetlen `pulses` sor-termelő a szintetikus `pulses:generate`
teszt-parancs volt), ezért biztonságos volt a tiszta csere:

- `app/Http/Controllers/Api/DeviceHelloController.php`,
  `DeviceAuthHelloController.php`, `DevicePulseController.php` – a
  `DeviceEnrollmentController`/`DevicePushController` váltja őket
  (self-enrollment + push egy kontraktban, one-shot parancs-kézbesítéssel).
- `app/Http/Middleware/DeviceTokenAuth.php` – a `DeviceApiKeyMiddleware`
  váltja (device_id + X-API-KEY, bcrypt-elt kulcs-ellenőrzés).
- `app/Http/Controllers/DeviceApiController.php` – egyetlen route sem
  hivatkozott rá sehol (megerősítve grep-pel), tisztán holt kód volt, és az
  `ack()` metódusa emellett egy valódi biztonsági rést is tartalmazott
  (bárki bármelyik parancsot lezárhatta ID-tallózással, eszköz-tulajdonlás
  ellenőrzése nélkül).
- `app/Console/Commands/GenerateDevicePulses.php` (`pulses:generate`) – a
  régi, egycsatornás (`count`/`delta`/`sample_id`) séma szintetikus
  teszt-adat-generátora volt; ezek az oszlopok a `pulses` táblából is
  törlésre kerültek (`2026_09_24_090002_drop_legacy_columns_from_pulses_table.php`).
- `app/Filament/Resources/DeviceResource/RelationManagers/FirmwaresRelationManager.php` –
  sosem volt regisztrálva a `DeviceResource::getRelations()`-ben (csak egy
  holt `use` import mutatott rá), tehát a felületen sosem jelent meg. A
  benne lévő `FileUpload` emellett nem is a `FirmwareResource` formjával
  egyező diskre mentett volna (nem adott meg explicit `disk()`-et, a
  globális default diskre esett volna vissza) – kettős okból holt/hibás
  kód volt.
- `App\Filament\Resources\FirmwareResource\Pages\CreateFirmware::afterCreate()`
  és `EditFirmware::afterSave()` – ugyanazt a fájl-metaadat-számítást
  (méret/mime/sha256) végezték el, amit a `Firmware::booted()::saved()`
  model-hook is elvégez minden mentésnél, csak a globális default disket
  (`CreateFirmware`) illetve explicit `'public'` disket (`EditFirmware`)
  nézték. Amíg a `FileUpload` maga is a `'public'` diskre mentett, ez
  véletlenül működött; a firmware-katalógus `'local'` (privát) diskre
  váltásakor ez a két duplikátum csendben semmit nem talált volna
  (disk-mismatch) – törölve a kódból (nem a fájlokból archiválva, mert
  method-body törlés volt, nem külön fájl), egyetlen kanonikus hely maradt
  a modellben.
- `app/Http/Controllers/DeviceController.php`, `PendingDeviceController.php`,
  `resources/views/livewire/devices/{index,create,edit}.blade.php`,
  `resources/views/livewire/dashboard.blade.php` – egy be nem fejezett,
  Filament-panelek előtti Breeze-kori eszközkezelő próbálkozás maradványai.
  Az `index.blade.php`-nek volt élő route-ja (`/devices`), de már ELŐZETESEN
  is 500-zal elszállt volna minden látogatónál, mert a `route('devices.create')`
  hívás egy sosem regisztrált route-ra mutatott (`devices.create`/`.edit`
  seholsem volt bejegyezve, csak ez a két üres Volt-stub fájl létezett
  hozzájuk, tartalom nélkül). A `dashboard.blade.php`-t semmi nem renderelte
  (a `dashboard` nevű route régóta csak a Filament panelekre irányít át,
  sosem ad vissza nézetet) -- a benne lévő `Pulse::sum('delta')` és
  `$p->count`/`$p->delta` hivatkozások a mostanra törölt legacy oszlopokra
  mutattak, de mivel a fájl amúgy sem futott le sosem, ez csak megerősítés,
  nem önmagában ok volt az archiválásra. A `devices.approve` route-nak (és a
  `PendingDeviceController::approve()`-nak) az egyetlen hívója ez a szintén
  soha nem renderelt dashboard-nézet volt -- a ténylegesen élő jóváhagyási
  felület a Filament `PendingDeviceResource` saját, beépített akciója,
  ami nem ezen a route-on/kontrolleren keresztül megy. A `routes/web.php`-ból
  a megfelelő route-bejegyzések (`/devices`, `/devices/approve/{pending}`)
  is törlésre kerültek.

## Élő, felhasználót érintő hibalánc feltárva és javítva (2026-09-24)

A teljes tesztsuite átvizsgálásakor (nem csak a pulse-counter munka során) egy
egymásra épülő hibalánc derült ki, ami **valós, élesben futó oldalakat**
érintett, nem csak teszteket:

1. **`bootstrap/app.php`** explicit `->withProviders([...])` tömbje sosem
   tartalmazta a `App\Providers\VoltServiceProvider`-t -- emiatt `Volt::mount()`
   sosem futott le, tehát **egyetlen Livewire Volt-komponens sem volt
   elérhető sehol az appban** (profil-űrlapok, stb.). Pótolva.
2. `resources/views/filament/pages/jump-code-form.blade.php` egy
   `<x-filament::alert>` komponenst használt, ami **a telepített Filament
   3.3-ban sosem is létezett** (ellenőrizve a teljes `vendor/filament`
   csomagban -- se PHP osztályként, se view-ként). Lecserélve egy sima,
   Filament szín-tokenekkel stílusozott divre.
3. `resources/views/partials/navigation.blade.php` (minden `<x-app-layout>`-ot
   használó oldalon megjelenő navsáv) két nemlétező route-ra hivatkozott:
   `route('jump-code-generator')` (a valódi név `jumpcodes.public`) és
   `route('logout')` (nincs egységes logout route, csak
   `filament.admin.auth.logout`/`filament.user.auth.logout` létezik
   panelenként). **Ez minden bejelentkezett felhasználónál elszállt volna
   bármelyik ilyen oldal (pl. `/profile`, `/machines`) meglátogatásakor.**
   Javítva.
4. `resources/views/layouts/app.blade.php` `@yield('content')`-et használt
   `{{ $slot }}` helyett a `<main>` elemben -- mivel `<x-app-layout>` egy
   **komponens** (`App\View\Components\AppLayout`), nem `@extends`-es
   sablon, a `@yield` sosem kapott tartalmat: **minden ezt használó oldal
   (`/profile`, `/machines`, `/machines/create`, `/machines/{id}/edit`)
   üres `<main>`-t renderelt**, a tényleges oldaltartalom (űrlapok, listák)
   sosem jelent meg. Javítva.
5. **`MachineController`** a `machines.index`/`machines.create`/
   `machines.edit` nézeteket várta (`resources/views/machines/*.blade.php`),
   de ez a mappa nem is létezett -- a tényleges fájlok tévesen a
   `resources/views/livewire/machines/` alatt voltak (`index.blade.php`,
   `create.blade.php`), `edit.blade.php` pedig **egyáltalán nem létezett**.
   A `/machines` oldal ezért minden látogatásnál "View not found" 500-as
   hibával elszállt volna. A két meglévő fájl áthelyezve a helyes útvonalra,
   az `edit.blade.php` pótolva (a `create.blade.php` mintájára,
   előtöltve a szerkesztett gép adataival). Új `tests/Feature/
   MachinesPagesTest.php` fedi mind a 4 műveletet (lista/create/edit/store),
   mert korábban egyáltalán nem volt teszt erre a funkcióra -- ezért maradt
   ez a hiba észrevétlen.

Mind az 5 pont valós, addig teszttel le nem fedett, élesben ténylegesen
elérhető oldalakat/funkciókat érintett -- nem csak a most archivált holt
kódot.

## Sosem bekötött Breeze-kori auth-útvonalak (`routes/auth.php`) (2026-09-24)

A `bootstrap/app.php` `->withRouting()` hívása csak a `web`/`api`/`commands`/
`health` route-fájlokat regisztrálja -- a `routes/auth.php` (a standard
Laravel Breeze scaffold auth-útvonalai: regisztráció, elfelejtett jelszó,
jelszó-visszaállítás, email-megerősítés, jelszó-megerősítés, valamint egy
Volt-alapú `/login`) **sosem volt sehonnan `require`-elve**, feltehetően
amikor a bejelentkezés a Filament panelekre lett átállítva (a `web.php`-beli
`/login` route explicit a Filament user-panel loginra irányít át), de ezt a
fájlt és a hozzá tartozó Volt-oldalakat elfelejtették törölni.

Egyik Filament panel sem engedélyezi a `->passwordReset()`/`->registration()`/
`->emailVerification()` funkciókat (csak `->login()`), tehát ezek a
funkciók ma **sehonnan nem érhetők el** -- egy elfelejtett jelszavú
felhasználónak az admin tud kézzel új jelszót beállítani a Filament
`UserResource` szerkesztőjén (`password` mező), ez az egyetlen létező
út. **Felhasználói döntés (2026-09-24): ez szándékos, marad így** -- nincs
szükség önkiszolgáló regisztrációra/jelszó-visszaállításra egy
admin-provisionelt HR-rendszeren, csak a holt kód archiválása történt meg,
funkcionális változtatás nélkül:

- `routes/auth.php`
- `resources/views/livewire/pages/auth/{login,register,forgot-password,
  reset-password,verify-email,confirm-password}.blade.php`
- `tests/Feature/Auth/{AuthenticationTest,EmailVerificationTest,
  PasswordConfirmationTest,PasswordResetTest,RegistrationTest}.php`

**Kivétel, NEM archiválva**: `tests/Feature/Auth/PasswordUpdateTest.php` --
ez a `livewire/profile/update-password-form.blade.php` élő, valódi Volt-
komponenst teszteli (a `/profile` oldalon, bejelentkezve a saját jelszavad
cseréje), ami a fenti hibalánc javítása után ténylegesen működik is
(zöld teszt).
