# ESP8266 darabszamlalo kliens (ATMENETI, kivezetesre szant)

**Ez a projekt szandekosan kulon all a `../esp32-counter` projekttol es
semmilyen forrasfajlt nem oszt meg vele.** Az ESP32 az elsodleges, hosszu
tavu celplatform ehhez az eszkozhoz -- ez a mappa csak addig el, amig az
utolso ESP8266-os panelt le nem cserelik. Amikor ez megtortenik, ez a mappa
egyszeruen torolheto, anelkul hogy barmit erintene az ESP32-projektben.

Ugyanazt a szerver-oldali JSON-kontraktot beszeli (enroll/push/backlog/
one-shot direktivak), mint az ESP32 kliens, de a sajat natív ESP8266
SDK-eszkozeivel megvalositva, mert a platform tenyleg mas:

| Terulet | Megvalositas |
|---|---|
| Impulzusszamlalas | GPIO-interrupt (`attachInterrupt`), szoftveres debounce -- nincs PCNT periferia ESP8266-on |
| Tartos konfig-tarolas | LittleFS + egy `/config.json` fajl -- nincs `Preferences.h` az ESP8266 core-ban |
| OTA-frissites | `ESP8266httpUpdate.h` -- az `X-API-KEY` URL query parameterkent megy, nem headerkent |
| OTA rollback-safety | **Nincs natív A/B-partícios garancia.** Csak egy szoftveres kozelites: 3 egymast koveto sikertelen boot ugyanarra a celverziora -> 24 orara felfuggesztve. A binarist nem tudja tenylegesen visszaallitani. |
| Watchdog | `Ticker`-alapu szoftveres megfelelo (120s inaktivitas -> ujrainditas), nem dedikalt HW task watchdog |

**Fontos hardveres korlat**: tipikus ESP8266 paneleken (pl. D1 Mini) kevesebb
megszakitas-kepes GPIO van, mint egy ESP32-n, es a D0 (GPIO16) egyaltalan
nem hasznalhato megszakitashoz. A PNP (aktiv-magas) bekotes tovabba kulso
pull-down ellenallast igenyel, mert ESP8266-on nincs altalanos belso
pull-down (csak a D0-n, ami viszont megszakitasra amugy sem hasznalhato).
Lasd `src/config_local.example.h` reszletes megjegyzeseit.

## Megnyitas es forditas

1. Nyisd meg a `firmware/esp8266-counter` mappat VS Code-ban.
2. Telepitsd a PlatformIO IDE kiegeszitot, ha meg nincs telepitve.
3. A PlatformIO a `platformio.ini` alapjan telepiti az ESP8266 platformot es
   az ArduinoJson fuggoseget.
4. Masold a `src/config_local.example.h` fajlt `src/config_local.h` nevre,
   toltsd ki a WiFi/API adatokat es ellenorizd/allitsd be a
   `PULSE_GPIO_D1..D4` / `PULSE_ACTIVE_LOW` erteket a tenyleges
   erzekelo-bekotesnek megfeleloen. A `config_local.h` gitignore-olt.
5. Az alapertelmezett celeszkoz egy `d1_mini` panel -- valtoztasd meg a
   `board` erteket a `platformio.ini`-ben, ha mas ESP8266-variansot
   hasznalsz.

A soros provisioning ugyanugy mukodik, mint az ESP32 projektben:

```text
set api_user=shared-enrollment-user
set api_pass=shared-enrollment-password
set hotspot_ssid=halozat-neve
set hotspot_pass=halozat-jelszava
```

OTA engedelyezesehez allits be egy nem ures `OTA_PASSWORD`-ot, tolts fel
egyszer USB-n, majd hasznald az `esp8266_ota` kornyezetet:

```powershell
pio run -e esp8266_ota -t upload
```

Az alkalmazas forrasa `src/main.cpp`.
