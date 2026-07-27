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
