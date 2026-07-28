# Dokumentációk

Ez a mappa a rendszerrel kapcsolatos dokumentációk (API-leírások, integrációs
specifikációk, stb.) és a funkció-változásnapló gyűjtőhelye.

## Tartalom

- [`CHANGELOG.md`](./CHANGELOG.md) — a rendszerben frissített/hozzáadott funkciók naplója, dátum szerint.
- [`terminal-webhook-api.md`](./terminal-webhook-api.md) — a beléptető terminál (`POST /api/terminal/event`)
  webhook API specifikációja, amit a szolgáltatónak lehet továbbítani. **Ez a forrás nem tartalmazza a
  valós titkos tokent** (helyette `{{TERMINAL_SECRET}}` placeholder szerepel benne) — a küldésre kész,
  valós tokent tartalmazó PDF-et a `php artisan docs:terminal-webhook-pdf` paranccsal lehet legenerálni.

## Konvenció

- Minden új dokumentáció ide kerül (`.md` forrás, szükség esetén generált `.pdf`).
- Élő titkot (token, jelszó, kulcs) tartalmazó **generált** dokumentum NEM kerül git-be — a forrás
  sablonban placeholder szerepel, a valós értéket tartalmazó kimenet a `.gitignore`-ban van.
- Új funkció/jelentős változás lezárásakor a `CHANGELOG.md`-be kerül egy rövid bejegyzés (dátum + mit
  csinál + miért).
