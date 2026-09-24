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
// GPIO pins used for the 4 pulse-counter channels (d1..d4). The defaults
// below are ESP32 input-capable pins that are safe to use as plain
// digital inputs on most esp32dev boards (avoid strapping pins 0/2/12/15
// and UART0 pins 1/3). Change to match the actual wiring.
//
// IMPORTANT: GPIO34/35 (D1/D2 below) are ESP32 "input-only" pins -- they
// have NO internal pull-up/pull-down hardware at all, unlike every other
// GPIO. With PNP sensors (see PULSE_ACTIVE_LOW below), these two channels
// are floating when the sensor is idle unless an EXTERNAL pull-down
// resistor (e.g. 10k to GND) is wired on D1/D2. GPIO32/33 (D3/D4) do not
// need an external resistor -- the firmware enables their internal
// pull-down in software -- but adding one anyway is good practice on a
// noisy factory floor (a discrete 4.7-10k resistor is a stronger pull
// than the chip's weak internal one).
const int PULSE_GPIO_D1 = 34;
const int PULSE_GPIO_D2 = 35;
const int PULSE_GPIO_D3 = 32;
const int PULSE_GPIO_D4 = 33;

// NPN proximity sensors are typically active-low (sink to GND when
// triggered) and need an internal pull-up; PNP sensors are typically
// active-high (source voltage when triggered) and need a pull-down.
// true  = NPN wiring: pull-up enabled, counts on the FALLING edge.
// false = PNP wiring: pull-down enabled, counts on the RISING edge.
// Confirmed with the user (2026-09-24): PNP opto sensors are used on all
// inputs -- keep this false. See the GPIO34/35 pull-down note above: those
// two channels need an external pull-down resistor with PNP wiring.
const bool PULSE_ACTIVE_LOW = false;

// PCNT hardware glitch filter, in APB clock cycles (80 MHz), 0-1023.
// 1000 cycles is about 12.5 microseconds -- filters out electrical noise
// / contact bounce shorter than that. Raise this if the sensors are noisy,
// lower it if genuinely fast pulses are being missed.
const uint16_t PULSE_DEBOUNCE_FILTER_CYCLES = 1000;
