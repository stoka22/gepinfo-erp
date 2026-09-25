# OTA binaries (ESP8266, atmeneti)

Built release binaries, kept here as the local staging point. Kulon
katalogus az esp32-counter binariaitol -- egy ESP8266-os es egy ESP32-os
bin SOSEM cserelheto fel, a szerver oldalon is kulon `platform` mezovel
kell megkulonboztetni oket.

A `.bin` fajlok gitignore-oltak. `pio run -e esp8266` ujraeloallitja oket,
az `extra_script.py` automatikusan ide masolja a build kimenetet, a
`src/main.cpp`-ben beallitott `FIRMWARE_VERSION` alapjan elnevezve.

## Aktualis

| Verzio | Fajl | MD5 |
|---|---|---|
| 1.0.0 | (meg nincs lefordítva) | - |
