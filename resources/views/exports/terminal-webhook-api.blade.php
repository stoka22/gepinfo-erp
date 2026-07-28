<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        p { margin: 4px 0; line-height: 1.4; }
        .meta { font-size: 9px; color: #666; margin-bottom: 16px; }
        code, pre { font-family: DejaVu Sans Mono, monospace; }
        pre { background: #f4f4f4; border: 1px solid #ddd; border-radius: 4px; padding: 8px; font-size: 10px; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 12px 0; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 10px; vertical-align: top; }
        th { background: #f2f2f2; }
        .token-box { background: #fff3cd; border: 1px solid #ffe08a; border-radius: 4px; padding: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Terminál webhook — API specifikáció</h1>
    <p class="meta">Generálva: {{ $generatedAt }} | Budafer Gyártó Kft. — gepinfo.hu</p>

    <h2>Végpont</h2>
    <pre>POST https://{{ $domain }}/api/terminal/event
Content-Type: application/json</pre>

    <h2>Hitelesítés</h2>
    <p>Minden kérésben kötelező egy megosztott titkos kulcsot küldeni HTTP fejlécben:</p>
    <pre>X-Auth-Token: <span class="token-box">{{ $token }}</span></pre>
    <p>Hiányzó vagy hibás token esetén a válasz <code>401 Unauthorized</code>.</p>

    <h2>Kérés törzse (JSON body)</h2>
    <table>
        <thead>
            <tr><th>Mező</th><th>Típus</th><th>Kötelező</th><th>Leírás</th></tr>
        </thead>
        <tbody>
            <tr><td><code>card_uid</code></td><td>string</td><td>igen</td><td>A dolgozói kártya egyedi azonosítója (UID), ahogy a terminál olvassa.</td></tr>
            <tr><td><code>direction</code></td><td>string</td><td>igen</td><td><code>"in"</code> = bejelentkezés, <code>"out"</code> = kijelentkezés. Csak ez a két érték fogadható el.</td></tr>
            <tr><td><code>timestamp</code></td><td>dátum-idő</td><td>igen</td><td>Az esemény pontos időpontja. Javasolt: ISO 8601, pl. <code>2026-07-28T08:00:00+02:00</code>. Ha nincs eltolás, magyar helyi időként (Europe/Budapest) értelmezzük. Explicit UTC/eltolás esetén automatikusan magyar időre konvertáljuk.</td></tr>
            <tr><td><code>event_id</code></td><td>string</td><td>opcionális, ajánlott</td><td>Egyedi esemény-azonosító a duplikált feldolgozás elkerülésére — hálózati hiba esetén ugyanazzal az ID-vel érdemes újraküldeni.</td></tr>
            <tr><td><code>location</code></td><td>string (max 255)</td><td>opcionális</td><td>A bejelentkezés helyszíne/telephelye, pl. "Gyártócsarnok 1". Csak bejelentkezéskor (<code>direction=in</code>) kerül rögzítésre.</td></tr>
        </tbody>
    </table>

    <h2>Példa kérés — bejelentkezés</h2>
    <pre>{
  "card_uid": "04A3B2C1",
  "direction": "in",
  "timestamp": "2026-07-28T08:00:00+02:00",
  "event_id": "evt-2026072800123",
  "location": "Gyártócsarnok 1"
}</pre>

    <h2>Példa kérés — kijelentkezés</h2>
    <pre>{
  "card_uid": "04A3B2C1",
  "direction": "out",
  "timestamp": "2026-07-28T16:30:00+02:00",
  "event_id": "evt-2026072800456"
}</pre>
    <p>(Kijelentkezésnél a <code>location</code> mezőt nem kell küldeni, nem kerül feldolgozásra.)</p>

    <h2>Válaszok</h2>
    <table>
        <thead>
            <tr><th>HTTP kód</th><th>Válasz body</th><th>Jelentés</th></tr>
        </thead>
        <tbody>
            <tr><td>200</td><td><code>{"ok": true}</code></td><td>Sikeres feldolgozás.</td></tr>
            <tr><td>200</td><td><code>{"ok": true, "duplicate": true}</code></td><td>Ezt az event_id-t már feldolgoztuk korábban — nem történt új rögzítés (normális, nem hiba).</td></tr>
            <tr><td>200</td><td><code>{"ok": true, "ignored": "already_checked_in"}</code></td><td>Bejelentkezés érkezett, de már van nyitott jelenlét-bejegyzés — figyelmen kívül hagyva.</td></tr>
            <tr><td>401</td><td><code>{"ok": false, "error": "unauthorized"}</code></td><td>Hiányzó vagy hibás X-Auth-Token.</td></tr>
            <tr><td>404</td><td><code>{"ok": false, "error": "unknown_card"}</code></td><td>A card_uid nem ismert, vagy nincs dolgozóhoz rendelve.</td></tr>
            <tr><td>409</td><td><code>{"ok": false, "error": "no_open_entry"}</code></td><td>Kijelentkezés érkezett, de nincs nyitott bejelentkezés az adott dolgozóhoz.</td></tr>
            <tr><td>422</td><td>Laravel validációs hiba</td><td>Hiányzó kötelező mező, vagy érvénytelen direction/timestamp formátum.</td></tr>
            <tr><td>500</td><td><code>{"ok": false, "error": "no_system_user"}</code></td><td>Belső rendszerhiba — a mi oldalunkon jelzendő probléma.</td></tr>
        </tbody>
    </table>

    <h2>Egyéb technikai megkötések</h2>
    <p>
        <strong>Rate limit:</strong> percenként max. 120 kérés IP-nként.<br>
        <strong>Idempotencia:</strong> hálózati hiba/timeout esetén ugyanazzal az event_id-vel érdemes újraküldeni a kérést.<br>
        <strong>Egy dolgozónak egyszerre csak egy nyitott jelenlét-bejegyzése lehet</strong> — két egymást követő "in" esemény között mindig kell egy "out" esemény.
    </p>
</body>
</html>
