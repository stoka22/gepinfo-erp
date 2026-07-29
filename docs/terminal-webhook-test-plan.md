# Terminál webhook — élesben futtatható tesztterv

> Forrás sablon — a valós tokent NEM tartalmazza (lásd `{{TERMINAL_SECRET}}` placeholder).
> A küldésre kész, valós tokent tartalmazó PDF-et a `php artisan docs:terminal-webhook-test-plan-pdf`
> paranccsal generáld le (`docs/terminal-webhook-test-plan.pdf`, git-ből kizárva).

Cél: ellenőrizni, hogy az éles (`https://gepinfo.hu`) terminál webhook végpont
(`POST /api/terminal/event`) valóban rögzíti a be-/kilépést, felismeri a duplikált
eseményeket (`event_id`), és a kilépés utáni idempotencia-javítás is működik.

A teszthez egy dedikált, könnyen azonosítható és a végén törölhető teszt cég, teszt
dolgozó és teszt kártya készül. (A `time_entries.company_id` mező kötelező, ezért
a dolgozóhoz mindenképp kell egy cég — ezért kap egy külön, a végén szintén törölhető
"TESZT Cég" rekordot, nem egy valós céghez adjuk hozzá.)

## 1. Teszt cég, dolgozó és kártya létrehozása

Futtasd a szerveren (SSH), a projekt gyökerében:

```bash
php artisan tinker
```

Majd illeszd be (Tinkerbe):

```php
$company = \App\Models\Company::create([
    'name' => 'TESZT Cég (webhook próba – törölhető)',
]);

$employee = \App\Models\Employee::create([
    'name' => 'TESZT Dolgozó (webhook próba – törölhető)',
    'company_id' => $company->id,
]);

$card = \App\Models\Card::create([
    'uid' => 'TESZT-WEBHOOK-0001',
    'label' => 'Webhook teszt kártya',
    'status' => 'assigned',
    'employee_id' => $employee->id,
]);

echo "company_id={$company->id} employee_id={$employee->id} card_uid={$card->uid}" . PHP_EOL;
exit
```

Jegyezd fel a kiírt `company_id`-t és `employee_id`-t (a kártya UID-ja fixen `TESZT-WEBHOOK-0001`).

## 2. Bejelentkezés (check-in) tesztelése

```bash
curl -i -X POST https://gepinfo.hu/api/terminal/event \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{TERMINAL_SECRET}}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "in",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-in-001",
    "location": "Webhook teszt"
  }'
```

**Elvárt válasz**: `200 OK`, body: `{"ok": true}`.

### 2a. Duplikált bejelentkezés (idempotencia)

Ugyanezt a kérést azonnal megismételve (ugyanaz az `event_id`):

```bash
curl -i -X POST https://gepinfo.hu/api/terminal/event \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{TERMINAL_SECRET}}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "in",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-in-001",
    "location": "Webhook teszt"
  }'
```

**Elvárt válasz**: `200 OK`, body: `{"ok": true, "duplicate": true}` — NEM jön létre új bejegyzés.

## 3. Kilépés (check-out) tesztelése

```bash
curl -i -X POST https://gepinfo.hu/api/terminal/event \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{TERMINAL_SECRET}}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "out",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-out-001"
  }'
```

**Elvárt válasz**: `200 OK`, body: `{"ok": true}`.

### 3a. A belépés event_id-jának újraküldése kilépés UTÁN (a legutóbbi idempotencia-javítás ellenőrzése)

```bash
curl -i -X POST https://gepinfo.hu/api/terminal/event \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{TERMINAL_SECRET}}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "in",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-in-001",
    "location": "Webhook teszt"
  }'
```

**Elvárt válasz**: `200 OK`, body: `{"ok": true, "duplicate": true}` — a rendszernek a
kilépés után is fel kell ismernie a belépés `event_id`-ját, és NEM szabad új
bejegyzést nyitnia.

## 4. Ellenőrzés adatbázis szinten

```bash
php artisan tinker
```

```php
\App\Models\TimeEntry::where('employee_id', $employeeId) // az 1. lépésben feljegyzett ID
    ->orderBy('id')
    ->get(['id','start_date','start_time','end_date','end_time','hours','location','note']);
```

Egyetlen sort kell látnod: kitöltött `start_time`/`end_time`, `location = "Webhook teszt"`,
és a `note` mezőben mindkét `event_id` szerepel (`terminal_event_id=test-in-001;terminal_event_id=test-out-001`).

## 5. Takarítás — a teszt dolgozó/kártya/bejegyzés törlése

```bash
php artisan tinker
```

```php
$employeeId = /* az 1. lépésben feljegyzett employee_id */;
$companyId = /* az 1. lépésben feljegyzett company_id */;

\App\Models\TimeEntry::where('employee_id', $employeeId)->forceDelete();
\App\Models\Card::where('uid', 'TESZT-WEBHOOK-0001')->forceDelete();
\App\Models\Employee::withTrashed()->find($employeeId)?->forceDelete();
\App\Models\Company::find($companyId)?->delete();

echo "Teszt adatok törölve." . PHP_EOL;
exit
```

## Hibaelhárítás

| Válasz | Jelentés |
|---|---|
| `401 unauthorized` | Hibás/hiányzó `X-Auth-Token` — ellenőrizd, hogy a PDF-ben lévő tokent másolod-e. |
| `404 unknown_card` | A kártya nem jött létre helyesen az 1. lépésben, vagy elgépelted a `card_uid`-t. |
| `409 no_open_entry` | Kilépést küldtél, de nincs nyitott belépés — futtasd újra a 2. lépést előbb. |
| `500 no_system_user` | Nincs egyetlen `role=admin` felhasználó sem az adatbázisban (nem várt, jelezd). |
