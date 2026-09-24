# OTA binaries

Built release binaries, kept here as the local staging point before
uploading to the gepinfo admin firmware-kezeles feluletre. Per-device
celzas, nincs flotta-szintu "push mindenkinek" kapcsolo, kovetve az Energy
projektben mar hozott operatori dontest.

A `.bin` fajlok gitignore-oltak (ujratermelheto build-kimenet -- `pio run
-e esp32dev` ujraeloallitja oket a megfelelo `src/` commitbol). Ez a fajl
csak dokumentalja, mi varhato ide es hogyan kerul be egy uj build.

## Uj build keszitese

```powershell
pio run -e esp32dev
```

Ennyi -- az `extra_script.py` (a `platformio.ini`-ben az `esp32dev`
kornyezethez regisztralva) automatikusan ide masolja a build kimenetet,
a `src/main.cpp`-ben forditaskor beallitott `FIRMWARE_VERSION` alapjan
elnevezve. Nincs kulon masolasi/atnevezesi lepes, a fajlnev sosem
csuszhat el a bininben ténylegesen futo verziotol.

A **release** kornyezetet hasznald (`esp32dev`), ne az `esp32dev_debug`-ot
-- a debug build `CORE_DEBUG_LEVEL=2`-t es reszletesebb naplozast hasznal,
amit uzemi eszkozoknek nem erdemes futtatniuk, es ez a script sincs ra
regisztralva.

## Aktualis

| Verzio | Fajl | MD5 |
|---|---|---|
| 1.0.0 | (meg nincs lefordítva) | - |

Bumpold a `FIRMWARE_VERSION`-t `src/main.cpp`-ben uj kiadas elott.
