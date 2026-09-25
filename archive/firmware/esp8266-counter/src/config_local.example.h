#pragma once

// Copy this file to config_local.h and fill in real values.
// config_local.h is gitignored -- never commit real credentials.

const char *HOTSPOT_SSID = "";
const char *HOTSPOT_PASS = "";

const char *API_URL = "https://example.invalid/api/device/push";
const char *API_BASIC_AUTH_USER = "shared-enrollment-user";
const char *API_BASIC_AUTH_PASS = "shared-enrollment-password";
const char *DEVICE_ID = "";
const char *API_KEY = "";
const char *OTA_PASSWORD = "";

// --- Pulse input wiring -----------------------------------------------
// NOT decided by the firmware architecture -- these depend entirely on
// the actual sensor wiring on each physical panel. Adjust per deployment.
//
// GPIO pins for the 4 pulse-counter channels. D0 (GPIO16) is deliberately
// NOT used here -- it has no interrupt controller on ESP8266 and cannot
// be used with attachInterrupt() at all. The defaults below (D1/D2/D5/D6)
// are commonly free, interrupt-capable pins on a Wemos D1 Mini-style
// board; adjust the numbers if a different board/pinout is used.
const int PULSE_GPIO_D1 = 5;  // D1
const int PULSE_GPIO_D2 = 4;  // D2
const int PULSE_GPIO_D3 = 14; // D5
const int PULSE_GPIO_D4 = 12; // D6

// NPN proximity sensors are typically active-low (sink to GND when
// triggered) and need a pull-up -- ESP8266 has an internal pull-up on
// every GPIO, so INPUT_PULLUP works directly.
// PNP sensors are typically active-high and need a pull-down -- ESP8266
// has NO internal pull-down on these pins (only GPIO16/D0 has one, and
// that pin can't be used for interrupts), so PNP wiring on this board
// REQUIRES an external pull-down resistor on each sensor input.
// true  = NPN wiring: internal pull-up, counts on the FALLING edge.
// false = PNP wiring: external pull-down required, counts on the RISING edge.
// Confirmed with the user (2026-09-24): PNP opto sensors are used on all
// inputs -- keep this false. Unlike the ESP32 project (where only D1/D2
// lack an internal pull-down), on ESP8266 NONE of D1/D2/D5/D6 have one --
// all 4 channels need their own external pull-down resistor (e.g. 10k to
// GND) with this wiring.
const bool PULSE_ACTIVE_LOW = false;

// Software debounce: minimum microseconds between two accepted edges on
// the same channel (ESP8266 has no hardware glitch filter like ESP32's
// PCNT, so this is the only line of defense against contact bounce).
const unsigned long PULSE_MIN_INTERVAL_US = 2000;
