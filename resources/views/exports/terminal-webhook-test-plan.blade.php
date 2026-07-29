<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 16px 0 6px 0; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        h3 { font-size: 11px; margin: 10px 0 4px 0; }
        p { margin: 4px 0; line-height: 1.4; }
        .meta { font-size: 9px; color: #666; margin-bottom: 16px; }
        .intro { background: #eef4ff; border: 1px solid #cddcf5; border-radius: 4px; padding: 8px; }
        code, pre { font-family: DejaVu Sans Mono, monospace; }
        pre { background: #f4f4f4; border: 1px solid #ddd; border-radius: 4px; padding: 8px; font-size: 9.5px; white-space: pre-wrap; word-wrap: break-word; }
        .expected { background: #eafaf0; border: 1px solid #bfe8cf; border-radius: 4px; padding: 6px 8px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 12px 0; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 10px; vertical-align: top; }
        th { background: #f2f2f2; }
        .token-box { background: #fff3cd; border: 1px solid #ffe08a; border-radius: 4px; padding: 2px 6px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Terminál webhook — élesben futtatható tesztterv</h1>
    <p class="meta">Generálva: {{ $generatedAt }} | Budafer Gyártó Kft. — gepinfo.hu</p>

    <p class="intro">
        Cél: ellenőrizni, hogy az éles ({{ $domain }}) terminál webhook végpont
        (<code>POST /api/terminal/event</code>) valóban rögzíti a be-/kilépést, felismeri a
        duplikált eseményeket (<code>event_id</code>), és a kilépés utáni idempotencia-javítás
        is működik. A teszthez egy dedikált, könnyen azonosítható és a végén törölhető teszt
        cég, teszt dolgozó és teszt kártya készül (a <code>time_entries.company_id</code> mező
        kötelező, ezért kell egy külön teszt cég is, nem valós céghez adjuk hozzá).
    </p>

    <h2>1. Teszt cég, dolgozó és kártya létrehozása</h2>
    <p>Futtasd a szerveren (SSH), a projekt gyökerében: <code>php artisan tinker</code>, majd:</p>
    <pre>$company = \App\Models\Company::create([
    'name' =&gt; 'TESZT Cég (webhook próba – törölhető)',
]);

$employee = \App\Models\Employee::create([
    'name' =&gt; 'TESZT Dolgozó (webhook próba – törölhető)',
    'company_id' =&gt; $company-&gt;id,
]);

$card = \App\Models\Card::create([
    'uid' =&gt; 'TESZT-WEBHOOK-0001',
    'label' =&gt; 'Webhook teszt kártya',
    'status' =&gt; 'assigned',
    'employee_id' =&gt; $employee-&gt;id,
]);

echo "company_id={$company-&gt;id} employee_id={$employee-&gt;id} card_uid={$card-&gt;uid}" . PHP_EOL;
exit</pre>
    <p>Jegyezd fel a kiírt <code>company_id</code>-t és <code>employee_id</code>-t (a kártya UID-ja fixen <code>TESZT-WEBHOOK-0001</code>).</p>

    <h2>2. Bejelentkezés (check-in) tesztelése</h2>
    <pre>curl -i -X POST {{ $endpoint }} \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{ $token }}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "in",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-in-001",
    "location": "Webhook teszt"
  }'</pre>
    <p class="expected">Elvárt válasz: <code>200 OK</code>, body: <code>{"ok": true}</code></p>

    <h3>2a. Duplikált bejelentkezés (idempotencia)</h3>
    <p>Ugyanezt a kérést azonnal megismételve (ugyanaz az <code>event_id</code>):</p>
    <pre>curl -i -X POST {{ $endpoint }} \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{ $token }}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "in",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-in-001",
    "location": "Webhook teszt"
  }'</pre>
    <p class="expected">Elvárt válasz: <code>200 OK</code>, body: <code>{"ok": true, "duplicate": true}</code> — NEM jön létre új bejegyzés.</p>

    <h2>3. Kilépés (check-out) tesztelése</h2>
    <pre>curl -i -X POST {{ $endpoint }} \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{ $token }}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "out",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-out-001"
  }'</pre>
    <p class="expected">Elvárt válasz: <code>200 OK</code>, body: <code>{"ok": true}</code></p>

    <h3>3a. A belépés event_id-jának újraküldése kilépés UTÁN</h3>
    <p>Ez ellenőrzi a legutóbbi idempotencia-javítást (a kilépés nem írja felül a belépés eseményazonosítóját):</p>
    <pre>curl -i -X POST {{ $endpoint }} \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: {{ $token }}" \
  -d '{
    "card_uid": "TESZT-WEBHOOK-0001",
    "direction": "in",
    "timestamp": "'"$(date +%Y-%m-%dT%H:%M:%S%:z)"'",
    "event_id": "test-in-001",
    "location": "Webhook teszt"
  }'</pre>
    <p class="expected">Elvárt válasz: <code>200 OK</code>, body: <code>{"ok": true, "duplicate": true}</code></p>

    <h2>4. Ellenőrzés adatbázis szinten</h2>
    <p><code>php artisan tinker</code>, majd:</p>
    <pre>\App\Models\TimeEntry::where('employee_id', $employeeId) // az 1. lépésben feljegyzett ID
    -&gt;orderBy('id')
    -&gt;get(['id','start_date','start_time','end_date','end_time','hours','location','note']);</pre>
    <p>Egyetlen sort kell látnod: kitöltött <code>start_time</code>/<code>end_time</code>,
        <code>location = "Webhook teszt"</code>, és a <code>note</code> mezőben mindkét
        <code>event_id</code> szerepel (<code>terminal_event_id=test-in-001;terminal_event_id=test-out-001</code>).</p>

    <h2>5. Takarítás — a teszt dolgozó/kártya/bejegyzés törlése</h2>
    <p><code>php artisan tinker</code>, majd:</p>
    <pre>$employeeId = /* az 1. lépésben feljegyzett employee_id */;
$companyId = /* az 1. lépésben feljegyzett company_id */;

\App\Models\TimeEntry::where('employee_id', $employeeId)-&gt;forceDelete();
\App\Models\Card::where('uid', 'TESZT-WEBHOOK-0001')-&gt;forceDelete();
\App\Models\Employee::withTrashed()-&gt;find($employeeId)?-&gt;forceDelete();
\App\Models\Company::find($companyId)?-&gt;delete();

echo "Teszt adatok törölve." . PHP_EOL;
exit</pre>

    <h2>Hibaelhárítás</h2>
    <table>
        <thead><tr><th>Válasz</th><th>Jelentés</th></tr></thead>
        <tbody>
            <tr><td><code>401 unauthorized</code></td><td>Hibás/hiányzó X-Auth-Token — ellenőrizd, hogy a fenti tokent másolod-e.</td></tr>
            <tr><td><code>404 unknown_card</code></td><td>A kártya nem jött létre helyesen az 1. lépésben, vagy elgépelted a card_uid-t.</td></tr>
            <tr><td><code>409 no_open_entry</code></td><td>Kilépést küldtél, de nincs nyitott belépés — futtasd újra a 2. lépést előbb.</td></tr>
            <tr><td><code>500 no_system_user</code></td><td>Nincs egyetlen role=admin felhasználó sem az adatbázisban (nem várt, jelezd).</td></tr>
        </tbody>
    </table>
</body>
</html>
