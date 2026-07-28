# Terminál webhook — API specifikáció a szolgáltató részére

> Forrás sablon — a valós tokent NEM tartalmazza (lásd `{{TERMINAL_SECRET}}` placeholder).
> A küldésre kész, valós tokent tartalmazó PDF-et a `php artisan docs:terminal-webhook-pdf`
> paranccsal generáld le (`docs/terminal-webhook-api.pdf`, git-ből kizárva).

## Végpont

```
POST https://gepinfo.hu/api/terminal/event
Content-Type: application/json
```

## Hitelesítés

Minden kérésben kötelező egy megosztott titkos kulcsot küldeni HTTP fejlécben:

```
X-Auth-Token: {{TERMINAL_SECRET}}
```

Hiányzó vagy hibás token esetén a válasz `401 Unauthorized`.

## Kérés törzse (JSON body)

| Mező | Típus | Kötelező | Leírás |
|---|---|---|---|
| `card_uid` | string | igen | A dolgozói kártya egyedi azonosítója (UID), ahogy a terminál olvassa. |
| `direction` | string | igen | `"in"` = bejelentkezés, `"out"` = kijelentkezés. Csak ez a két érték fogadható el. |
| `timestamp` | string (dátum-idő) | igen | Az esemény pontos időpontja. Javasolt formátum: ISO 8601, pl. `"2026-07-28T08:00:00+02:00"` vagy `"2026-07-28 08:00:00"`. Ha nincs explicit időzóna-eltolás, a rendszer magyar helyi időként (Europe/Budapest) értelmezi. Ha explicit eltolást vagy UTC-t (`Z`) küld, azt automatikusan magyar helyi időre konvertáljuk. |
| `event_id` | string | opcionális, de ajánlott | Egyedi esemény-azonosító a duplikált feldolgozás elkerülésére. Hálózati hiba esetén ugyanazzal az `event_id`-vel érdemes újraküldeni — a rendszer ilyenkor nem hoz létre új bejegyzést, csak visszaigazol. |
| `location` | string (max. 255 karakter) | opcionális | A bejelentkezés helyszíne/telephelye, pl. `"Gyártócsarnok 1"`, `"Iroda"`, `"Raktár"`. Csak bejelentkezéskor (`direction=in`) kerül rögzítésre. |

### Példa kérés — bejelentkezés

```json
{
  "card_uid": "04A3B2C1",
  "direction": "in",
  "timestamp": "2026-07-28T08:00:00+02:00",
  "event_id": "evt-2026072800123",
  "location": "Gyártócsarnok 1"
}
```

### Példa kérés — kijelentkezés

```json
{
  "card_uid": "04A3B2C1",
  "direction": "out",
  "timestamp": "2026-07-28T16:30:00+02:00",
  "event_id": "evt-2026072800456"
}
```

*(Kijelentkezésnél a `location` mezőt nem kell küldeni, nem kerül feldolgozásra.)*

## Válaszok

| HTTP kód | Válasz body | Jelentés |
|---|---|---|
| `200` | `{"ok": true}` | Sikeres feldolgozás. |
| `200` | `{"ok": true, "duplicate": true}` | Ezt az `event_id`-t már feldolgoztuk korábban — nem történt új rögzítés (normális, nem hiba). |
| `200` | `{"ok": true, "ignored": "already_checked_in"}` | Bejelentkezési kísérlet érkezett, de a dolgozónak már van nyitott jelenlét-bejegyzése — figyelmen kívül hagyva. |
| `401` | `{"ok": false, "error": "unauthorized"}` | Hiányzó vagy hibás `X-Auth-Token`. |
| `404` | `{"ok": false, "error": "unknown_card"}` | A `card_uid` nem ismert, vagy nincs dolgozóhoz rendelve. |
| `409` | `{"ok": false, "error": "no_open_entry"}` | Kijelentkezési esemény érkezett, de nincs nyitott bejelentkezés az adott dolgozóhoz. |
| `422` | Laravel validációs hiba részletekkel | Hiányzó kötelező mező, vagy érvénytelen `direction`/`timestamp` formátum. |
| `500` | `{"ok": false, "error": "no_system_user"}` | Belső rendszerhiba — a mi oldalunkon jelzendő probléma. |

## Egyéb technikai megkötések

- **Rate limit**: percenként max. 120 kérés IP-nként.
- **Idempotencia**: hálózati hiba/timeout esetén ugyanazzal az `event_id`-vel érdemes újraküldeni a kérést.
- **Egy dolgozónak egyszerre csak egy nyitott jelenlét-bejegyzése lehet** — két egymást követő "in"
  esemény között mindig kell egy "out" esemény, különben a második "in" figyelmen kívül lesz hagyva.
