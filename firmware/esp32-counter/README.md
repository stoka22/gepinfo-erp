# ESP32 darabszamlalo kliens (gepinfo /monitor)

Ez a projekt PlatformIO-projektkent van beallitva VS Code-hoz. Az ESP32 az
elsodleges, hosszu tavu celplatform ehhez az eszkozhoz -- a testver
ESP8266-os projekt (`../esp8266-counter`) egy kulon, atmeneti kivezetesre
szant kodbazis, ezzel semmilyen forrasfajlt nem oszt meg.

Egy panel akar 4 fuggetlen bemeneti csatornat (d1-d4) szamlal NPN/PNP
kozelitéskapcsolokrol, ESP32 hardveres PCNT periferiaval. A szerver oldalon
mindegyik csatorna kulon-kulon egy-egy gephez rendelheto (lasd a gepinfo
admin feluleten a "Csatornak" beallitast egy eszkozon).

## Megnyitas es forditas

1. Nyisd meg az `firmware/esp32-counter` mappat VS Code-ban.
2. Telepitsd a PlatformIO IDE kiegeszitot, ha meg nincs telepitve.
3. A PlatformIO a `platformio.ini` alapjan telepiti az ESP32 platformot es
   az ArduinoJson fuggoseget.
4. Hasznald a PlatformIO eszkoztarat forditashoz, feltoltéshez, vagy a
   soros monitor megnyitasahoz.

Forditas elott masold a `src/config_local.example.h` fajlt
`src/config_local.h` nevre, es toltsd ki a helyi WiFi/API adatokat, valamint
a `PULSE_GPIO_D1..D4` / `PULSE_ACTIVE_LOW` / `PULSE_DEBOUNCE_FILTER_CYCLES`
erteket a tenyleges erzekelo-bekotesnek megfeleloen. A `config_local.h`
fajlt a Git figyelmen kivul hagyja, nem szabad commitolni.

A firmware eszkoz-specifikus ertekeket ESP32 NVS-ben tarolja, ami tulel egy
normal OTA-frissitest. A soros monitort 115200-on megnyitva, egy mar
felprogramozott eszkoz ujraforditas nelkul konfiguralhato:

```text
set api_user=shared-enrollment-user
set api_pass=shared-enrollment-password
set hotspot_ssid=halozat-neve
set hotspot_pass=halozat-jelszava
```

A firmware a `device_id`-t `ESP32_<MAC>` alakban vezeti le, es enrollment
kereskor a szerver egy egyedi `api_key`/`ota_pass` parat ad vissza, amit az
eszkoz az NVS-be ment. Ugyanaz a leforditott firmware-bin igy tobb
eszkozon is hasznalhato, egyedi per-eszkoz build nelkul.

A szerver oldalon uj eszkoz csak admin-jovahagyas utan kap kulcsot -- egy
meg nem jovahagyott `device_id` `/enroll` hivasa `202`-t kap ("varolistan"),
es a szerver a `PendingDevice` listaban jelenik meg jovahagyasra varva.

Az OTA engedelyezesehez allits be egy nem ures `OTA_PASSWORD`-ot a
`src/config_local.h`-ban, tolts fel egyszer USB-n keresztul, majd hasznald
az `esp32dev_ota` kornyezetet. A firmware `<device_id>.local` nevkent
hirdeti magat.

```powershell
pio run -e esp32dev_ota -t upload
```

Az OTA kornyezet feltetelezi, hogy `<device_id>.local` elerheto ugyanazon a
halozaton. Az elso USB-s feltoltes mindig szukseges -- OTA nem tud
helyreallitani egy torolt vagy nem futo eszkozt.

Az alapertelmezett celeszkoz egy `esp32dev` panel, 115200 baud-on. Valtoztasd
meg a `board` erteket a `platformio.ini`-ben, ha mas ESP32-variansot hasznalsz.

Az alkalmazas forrasa `src/main.cpp`. A tavoli firmware-frissites protokollja
megegyezik a testver "Energy" projektevel (self-enrollment, one-shot
reboot/provisioning/firmware-target kezbesites, MD5-ellenorzott OTA,
rollback-safety, offline backlog, exponencialis backoff, hardveres
watchdog) -- csak a mert adat mas: Modbus energiamérés helyett d1-d4
impulzus-osszegek.
