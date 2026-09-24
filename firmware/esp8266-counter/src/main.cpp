#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <ESP8266httpUpdate.h>
#include <ArduinoOTA.h>
#include <ArduinoJson.h>
#include <LittleFS.h>
#include <Ticker.h>
#include <time.h>
#include "config_local.h"

// ===========================================================================
// ATMENETI, KIVEZETESRE SZANT PROJEKT
// ===========================================================================
// Ez a firmware szandekosan KULON all a ../esp32-counter projekttol, es NEM
// oszt meg vele forraskodot. Az ESP32 az elsodleges, hosszu tavu celplatform
// -- ez itt csak addig el, amig az utolso ESP8266-os panelt le nem cserelik.
// Ahol az ESP8266 SDK nem ad natively olyan garanciat, mint az ESP32
// (valodi A/B-partícios OTA rollback, hardveres PCNT impulzusszamlalo,
// dedikalt HW task watchdog), ott egy dokumentaltan gyengebb, de
// mukodo szoftveres kozelites van -- lasd az egyes fuggvenyek meletti
// megjegyzeseket. Ez tudatos dontes, nem hiba: nem eri meg ugyanazt a
// mernoki melyseget belefektetni egy kivezetesre szant vonalba.
// ===========================================================================

const char *FIRMWARE_VERSION = "1.0.0";

// Root CA a firmware-letoltő kapcsolathoz (pinnelve, nem setInsecure()) --
// azonos az esp32-counter projektben hasznalt Let's Encrypt ISRG Root X2-vel.
// Ellenorizd, hogy egyezik a tenyleges szerver tanusitvany-lancaval.
const char *FIRMWARE_CA_CERT = R"CERT(
-----BEGIN CERTIFICATE-----
MIIEcDCCAligAwIBAgIQbI8dxyfHEX97r4U6yYD5zTANBgkqhkiG9w0BAQsFADBP
MQswCQYDVQQGEwJVUzEpMCcGA1UEChMgSW50ZXJuZXQgU2VjdXJpdHkgUmVzZWFy
Y2ggR3JvdXAxFTATBgNVBAMTDElTUkcgUm9vdCBYMTAeFw0yNjA1MTMwMDAwMDBa
Fw0zMjA5MDIyMzU5NTlaME8xCzAJBgNVBAYTAlVTMSkwJwYDVQQKEyBJbnRlcm5l
dCBTZWN1cml0eSBSZXNlYXJjaCBHcm91cDEVMBMGA1UEAxMMSVNSRyBSb290IFgy
MHYwEAYHKoZIzj0CAQYFK4EEACIDYgAEzZvVn4CDCuwJSvMWSj5cz3es3mcFDR0H
ttwW+1qLFNvicWDEukWVEYmO6gbf9yoWHKS5xcUy4APgHoIYOIvXRdgKam7mAHf7
AlF9ItgKbppbd9/w+kHsOdx1ymgHDB/qo4H1MIHyMA4GA1UdDwEB/wQEAwIBBjAd
BgNVHSUEFjAUBggrBgEFBQcDAQYIKwYBBQUHAwIwDwYDVR0TAQH/BAUwAwEB/zAd
BgNVHQ4EFgQUfEKWrt5LSDv6kviejM9ti6lyN5UwHwYDVR0jBBgwFoAUebRZ5nu2
5eQBc4AIiMgaWPbpm24wMgYIKwYBBQUHAQEEJjAkMCIGCCsGAQUFBzAChhZodHRw
Oi8veDEuaS5sZW5jci5vcmcvMBMGA1UdIAQMMAowCAYGZ4EMAQIBMCcGA1UdHwQg
MB4wHKAaoBiGFmh0dHA6Ly94MS5jLmxlbmNyLm9yZy8wDQYJKoZIhvcNAQELBQAD
ggIBAD2/e9frmMxNpCV03qUHegg+MV2wz9644YoXdqtH8RyWYcBO7xfjjGEXdU1e
/o0OkEFiynUCOSIk/vLLo7ttz6CPAeNlWfC0XNkoGeWgK6jjXvozBaGuGH5n0Ufo
shMeWTuURqNN5G00sSXDTBrpp2+mgvdZQjb8K11TYMA25QA+YHNfbIEL0BniAhKS
2gsnJjSzrdZLI+EZ7SEyqdR2rkjd1KutLDU+n3TFyxjniZVGur4YlhMP3mY/dV95
IruAkkjOZier6hGBdEgZXXvaCz9u9iVEadsIE75pAGL8oHV5vxdARDiotRpul1IN
/UZwzAbrfUFcw1HkAcYD/mlZfnQ2ieCF2MS7j3Vhv7JPDKp45fmykmzYNSrumRW0
upFFKDBOoF7hsOb7oLyHS+Uft6jOUfOrogj8YUx38hKb2K20r42OgsSdDdxdeYWc
MS3Sb6mwJeSZEYxJ2gaXnDSPaKhhrNkYwljyVQyr4Nq+MEJytXNTnHqaAcrNwZlV
pcJL1KBnMrMjP7eanvUwL3FYj3cF17jtboLt7gLoi4+2rWZFvn+w54jmd/FIuhhZ
cEaU/wvU6BUNMtcVquVGHp7itQeDth5j+XL3j4WJ2SABwzUl6OeYdgpIt/ITZa+p
TT0mQ/r5XyA4MEAiabn7XJjvCERlF2dcn2wqJw+CreTkkQ2R
-----END CERTIFICATE-----
)CERT";

// --- Szoftveres watchdog (nincs dedikalt HW task watchdog ESP8266-on) ---
Ticker wdTicker;
volatile uint16_t wdSecondsSinceFeed = 0;
const uint16_t WATCHDOG_TIMEOUT_S = 120;

void ICACHE_RAM_ATTR wdTick() { wdSecondsSinceFeed++; }
void feedWatchdog() { wdSecondsSinceFeed = 0; }
void checkWatchdog()
{
	if (wdSecondsSinceFeed > WATCHDOG_TIMEOUT_S)
	{
		Serial.println("Szoftveres watchdog timeout, ujrainditas.");
		ESP.restart();
	}
}

// --- Tartos konfig-tarolas: LittleFS + egy /config.json fajl -----------
// Nincs Preferences.h az ESP8266 core-ban -- ez helyettesiti, ugyanazzal
// a "elso bootkor a compile-time alapertekkel feltoltjuk, utana a fajl az
// iranyado" logikaval, mint az ESP32-projekt NVS-e.
const char *CONFIG_PATH = "/config.json";
DynamicJsonDocument configDoc(4096);

void configLoad()
{
	if (!LittleFS.begin())
	{
		Serial.println("LittleFS mount sikertelen, formazas...");
		LittleFS.format();
		LittleFS.begin();
	}
	if (LittleFS.exists(CONFIG_PATH))
	{
		File f = LittleFS.open(CONFIG_PATH, "r");
		DeserializationError err = deserializeJson(configDoc, f);
		f.close();
		if (err)
		{
			Serial.println("Config JSON hibas, ures configgal indulunk.");
			configDoc.clear();
		}
	}
}

void configSave()
{
	File f = LittleFS.open(CONFIG_PATH, "w");
	serializeJson(configDoc, f);
	f.close();
}

bool configIsKey(const char *key) { return configDoc.containsKey(key); }

String configGetString(const char *key, const char *fallback)
{
	if (configDoc.containsKey(key))
	{
		const char *v = configDoc[key];
		if (v != nullptr)
			return String(v);
	}
	return String(fallback);
}

void configPutString(const char *key, const String &value)
{
	configDoc[key] = value;
	configSave();
}

void configRemove(const char *key)
{
	configDoc.remove(key);
	configSave();
}

String loadConfigValue(const char *key, const char *fallback)
{
	String stored = configIsKey(key) ? configGetString(key, "") : String("");
	if (stored.isEmpty() && fallback != nullptr && strlen(fallback) > 0)
	{
		configPutString(key, fallback);
		return String(fallback);
	}
	return stored;
}

String runtimeHotspotSsid;
String runtimeHotspotPass;
String runtimeApiUrl;
String runtimeApiBasicAuthUser;
String runtimeApiBasicAuthPass;
String runtimeDeviceId;
String runtimeApiKey;
String runtimeOtaPassword;
unsigned int apiAuthFailureCount = 0;
const unsigned int MAX_API_AUTH_FAILURES = 10;
unsigned long lastOnlinePush = 0;
unsigned long lastHeartbeat = 0;
const unsigned long HEARTBEAT_INTERVAL_MS = 60000;
bool wifiScanPending = false;
bool otaStarted = false;
bool restartRequested = false;
bool firmwareValidated = false;

unsigned long lastFirmwareAttemptMs = 0;
unsigned int firmwareAttemptCount = 0;
String lastFirmwareAttemptVersion;
const unsigned long FIRMWARE_RETRY_BASE_MS = 60000;
const unsigned long FIRMWARE_RETRY_MAX_MS = 1800000;

const int MAX_BACKLOG_SAMPLES = 5;

// WiFi strategia (azonos elv, mint az esp32-counter / Energy referencia-kliens):
//   - A HOTSPOT_* mindig a beegetett, garantalt tartalek halozat.
//   - A szerver sikeres POST valaszban wifi_networks listat kuldhet.
//   - A halozatok a config.json-ba kerulnek, majd ujracsatlakozaskor sorrendben probalodnak.
//   - Ha egyik preferalt halozat sem erheto el, a kliens a hotspotra esik vissza.
const int MAX_PREFERRED_NETWORKS = 5;
const int MAX_WIFI_SCAN_RESULTS = 5;
const int MAX_WIFI_SCAN_NETWORKS = 48;
struct WifiCandidate
{
	String ssid;
	String password;
};
WifiCandidate preferredNetworks[MAX_PREFERRED_NETWORKS];
int preferredNetworkCount = 0;

struct WifiObservation
{
	String ssid;
	int32_t rssi;
};
WifiObservation wifiScanResults[MAX_WIFI_SCAN_RESULTS];
int wifiScanResultCount = 0;

void initializeWifiRadio()
{
	WiFi.mode(WIFI_STA);
	WiFi.setOutputPower(20.5);
	WiFi.setSleepMode(WIFI_NONE_SLEEP);
	Serial.println("WiFi radio inicializalva (ESP8266, teljes adoteljesitmeny, sleep kikapcsolva).");
}

const char *resetReasonString()
{
	switch (ESP.getResetInfoPtr()->reason)
	{
	case REASON_DEFAULT_RST:
		return "poweron";
	case REASON_WDT_RST:
		return "wdt";
	case REASON_EXCEPTION_RST:
		return "panic";
	case REASON_SOFT_WDT_RST:
		return "task_wdt";
	case REASON_SOFT_RESTART:
		return "sw_reset";
	case REASON_DEEP_SLEEP_AWAKE:
		return "deepsleep";
	case REASON_EXT_SYS_RST:
		return "ext_reset";
	default:
		return "unknown";
	}
}

String hardwareDeviceId()
{
	// A szerver (DeviceEnrollmentController::normalizeDeviceId) pontosan 12
	// hex jegyu, teljes 6 bajtos MAC-cimet var "ESP8266_<12 HEX>" alakban --
	// az ESP.getChipId() csak a MAC also 3 bajtjabol szarmazik (6 jegy), az
	// NEM eleg, "Malformed device_id" 422-t eredmenyez. A teljes STA MAC-et
	// hasznaljuk, ugyanugy mint amit a WiFi radio ambient azonositokent ad.
	WiFi.mode(WIFI_STA);
	uint8_t mac[6];
	WiFi.macAddress(mac);
	char buffer[24];
	snprintf(buffer, sizeof(buffer), "ESP8266_%02X%02X%02X%02X%02X%02X",
			 mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
	return String(buffer);
}

void initializeConfigDefaults()
{
	const struct
	{
		const char *key;
		const char *value;
	} defaults[] = {
		{"api_url", API_URL},
		{"device_id", DEVICE_ID},
		{"api_user", API_BASIC_AUTH_USER},
		{"api_pass", API_BASIC_AUTH_PASS},
		{"api_key", API_KEY},
		{"ota_pass", OTA_PASSWORD},
	};

	bool initialized = false;
	for (const auto &item : defaults)
	{
		if (!configIsKey(item.key) && item.value != nullptr && strlen(item.value) > 0)
		{
			configDoc[item.key] = item.value;
			initialized = true;
		}
	}
	if (initialized)
	{
		configSave();
		Serial.println("Config alapertekek inicializalva.");
	}
}

void loadRuntimeConfig()
{
	String generatedDeviceId = hardwareDeviceId();
	runtimeHotspotSsid = HOTSPOT_SSID;
	runtimeHotspotPass = HOTSPOT_PASS;
	runtimeApiUrl = loadConfigValue("api_url", API_URL);
	runtimeApiBasicAuthUser = loadConfigValue("api_user", API_BASIC_AUTH_USER);
	runtimeApiBasicAuthPass = loadConfigValue("api_pass", API_BASIC_AUTH_PASS);
	runtimeDeviceId = loadConfigValue("device_id", generatedDeviceId.c_str());
	runtimeApiKey = loadConfigValue("api_key", API_KEY);
	runtimeOtaPassword = loadConfigValue("ota_pass", OTA_PASSWORD);
	apiAuthFailureCount = (unsigned int)configGetString("api_401_count", "0").toInt();
	Serial.printf("Futasi konfiguracio betoltve: device_id=%s\n", runtimeDeviceId.c_str());
	if (runtimeDeviceId.isEmpty() || runtimeApiKey.isEmpty() ||
		runtimeApiBasicAuthUser.isEmpty() || runtimeApiBasicAuthPass.isEmpty())
		Serial.println("HIBA: hianyos API konfiguracio. Hasznald a 'set' provisioning parancsokat.");
}

bool enrollDevice()
{
	if (WiFi.status() != WL_CONNECTED || runtimeApiBasicAuthUser.isEmpty() || runtimeApiBasicAuthPass.isEmpty())
		return false;

	String enrollmentUrl = runtimeApiUrl;
	enrollmentUrl.replace("/device/push", "/device/enroll");

	{
		IPAddress resolvedIp;
		int hostStart = enrollmentUrl.indexOf("://") + 3;
		int hostEnd = enrollmentUrl.indexOf('/', hostStart);
		String hostOnly = enrollmentUrl.substring(hostStart, hostEnd < 0 ? enrollmentUrl.length() : hostEnd);
		if (WiFi.hostByName(hostOnly.c_str(), resolvedIp))
			Serial.printf("DNS: %s -> %s\n", hostOnly.c_str(), resolvedIp.toString().c_str());
		else
			Serial.printf("DNS FELOLDAS SIKERTELEN: %s\n", hostOnly.c_str());
	}

	StaticJsonDocument<256> requestDoc;
	requestDoc["device_id"] = runtimeDeviceId;
	String payload;
	serializeJson(requestDoc, payload);

	BearSSL::WiFiClientSecure client;
	client.setInsecure();
	client.setBufferSizes(1024, 1024);
	HTTPClient http;
	if (!http.begin(client, enrollmentUrl))
	{
		Serial.println("Enrollment: http.begin() sikertelen (rossz URL?).");
		return false;
	}
	http.addHeader("Content-Type", "application/json");
	http.setAuthorization(runtimeApiBasicAuthUser.c_str(), runtimeApiBasicAuthPass.c_str());
	int code = http.POST(payload);
	String response = http.getString();
	Serial.printf("Enrollment kuldott payload: %s\n", payload.c_str());
	Serial.printf("Enrollment code=%d, HTTPClient hiba: %s\n", code, http.errorToString(code).c_str());
	Serial.printf("Enrollment valasz body: %s\n", response.c_str());
	if (code < 0)
	{
		char sslError[128];
		int sslErrorCode = client.getLastSSLError(sslError, sizeof(sslError));
		Serial.printf("BearSSL utolso hiba (%d): %s\n", sslErrorCode, sslError);
	}
	http.end();

	if (code == 202)
	{
		Serial.println("Enrollment fuggoben: az eszkoz meg nincs admin altal jovahagyva.");
		return false;
	}
	if (code < 200 || code >= 300)
		return false;

	StaticJsonDocument<768> responseDoc;
	if (deserializeJson(responseDoc, response) != DeserializationError::Ok)
		return false;
	const char *apiKey = responseDoc["api_key"];
	const char *otaPass = responseDoc["ota_pass"];
	if (!apiKey || !otaPass || strlen(apiKey) == 0 || strlen(otaPass) == 0)
		return false;

	configPutString("api_key", apiKey);
	configPutString("ota_pass", otaPass);
	runtimeApiKey = apiKey;
	runtimeOtaPassword = otaPass;
	Serial.println("Sikeres enrollment, egyedi API-kulcs es OTA-jelszo elmentve.");
	return true;
}

void recordApiAuthenticationResult(int httpCode)
{
	if (httpCode == 401)
	{
		apiAuthFailureCount++;
		configPutString("api_401_count", String(apiAuthFailureCount));
		Serial.printf("API hitelesitesi hiba: %u/%u\n", apiAuthFailureCount, MAX_API_AUTH_FAILURES);
		if (apiAuthFailureCount >= MAX_API_AUTH_FAILURES)
		{
			configRemove("api_key");
			runtimeApiKey = "";
			apiAuthFailureCount = 0;
			configPutString("api_401_count", "0");
			Serial.println("Az API kulcs 10 egymast koveto 401 hiba utan torolve. Uj provisioning szukseges.");
		}
		return;
	}
	if (httpCode >= 200 && httpCode < 300)
	{
		apiAuthFailureCount = 0;
		configPutString("api_401_count", "0");
	}
}

void saveRuntimeConfigValue(const String &key, const String &value)
{
	if (key == "hotspot_ssid" || key == "hotspot_pass")
	{
		Serial.println("Az alapertelmezett hotspot forraskodban rogzitett, nem modosithato.");
		return;
	}
	static const char *allowed[] = {"api_url", "api_user", "api_pass", "device_id", "api_key", "ota_pass"};
	bool ok = false;
	for (const char *k : allowed)
		if (key == k)
		{
			ok = true;
			break;
		}
	if (!ok)
	{
		Serial.println("Ismeretlen konfiguracios kulcs.");
		return;
	}
	configPutString(key.c_str(), value);
	loadRuntimeConfig();
	Serial.println("Konfiguracio elmentve. Az uj ertekekhez inditsd ujra az eszkozt.");
}

void handleSerialProvisioning()
{
	if (!Serial.available())
		return;
	String command = Serial.readStringUntil('\n');
	command.trim();
	if (!command.startsWith("set "))
		return;
	int separator = command.indexOf('=', 4);
	if (separator <= 4)
	{
		Serial.println("Hasznalat: set kulcs=ertek");
		return;
	}
	String key = command.substring(4, separator);
	String value = command.substring(separator + 1);
	key.trim();
	value.trim();
	saveRuntimeConfigValue(key, value);
}

// --- Impulzus-szamlalas: GPIO-interrupt (nincs PCNT periferia ESP8266-on) ---
// Szoftveres debounce: minden elnel megnezzuk, hogy a legutobbi elfogadott
// el ota eltelt-e legalabb PULSE_MIN_INTERVAL_US mikroszekundum. Kulon,
// argumentum nelkuli ISR-eket hasznalunk csatornankent a legszelesebb
// ESP8266-core-kompatibilitas erdekeben (attachInterruptArg nem mindenhol
// elerheto).

const int PULSE_CHANNEL_COUNT = 4;
const int PULSE_GPIOS[PULSE_CHANNEL_COUNT] = {PULSE_GPIO_D1, PULSE_GPIO_D2, PULSE_GPIO_D3, PULSE_GPIO_D4};

volatile uint32_t isrCounts[PULSE_CHANNEL_COUNT] = {0, 0, 0, 0};
volatile unsigned long lastEdgeMicros[PULSE_CHANNEL_COUNT] = {0, 0, 0, 0};
uint32_t pulseTotals[PULSE_CHANNEL_COUNT] = {0, 0, 0, 0};

void ICACHE_RAM_ATTR handlePulseEdge(int idx)
{
	unsigned long now = micros();
	if (now - lastEdgeMicros[idx] < PULSE_MIN_INTERVAL_US)
		return;
	lastEdgeMicros[idx] = now;
	isrCounts[idx]++;
}
void ICACHE_RAM_ATTR isrPulse0() { handlePulseEdge(0); }
void ICACHE_RAM_ATTR isrPulse1() { handlePulseEdge(1); }
void ICACHE_RAM_ATTR isrPulse2() { handlePulseEdge(2); }
void ICACHE_RAM_ATTR isrPulse3() { handlePulseEdge(3); }

void initGpioInterruptChannels()
{
	void (*isrs[PULSE_CHANNEL_COUNT])() = {isrPulse0, isrPulse1, isrPulse2, isrPulse3};
	for (int i = 0; i < PULSE_CHANNEL_COUNT; i++)
	{
		// PNP (active-high) bekotesnel ESP8266-on nincs altalanos belso
		// pull-down -- kulso ellenallas szukseges a szenzor bemenetén.
		pinMode(PULSE_GPIOS[i], PULSE_ACTIVE_LOW ? INPUT_PULLUP : INPUT);
		attachInterrupt(digitalPinToInterrupt(PULSE_GPIOS[i]), isrs[i],
						 PULSE_ACTIVE_LOW ? FALLING : RISING);
	}
	Serial.println("GPIO-interrupt impulzusszamlalo csatornak inicializalva.");
}

void accumulatePulseCounters()
{
	noInterrupts();
	for (int i = 0; i < PULSE_CHANNEL_COUNT; i++)
	{
		pulseTotals[i] += isrCounts[i];
		isrCounts[i] = 0;
	}
	interrupts();
}

struct PulseSample
{
	uint32_t total[PULSE_CHANNEL_COUNT];
};

PulseSample readPulseCounters()
{
	accumulatePulseCounters();
	PulseSample s;
	for (int i = 0; i < PULSE_CHANNEL_COUNT; i++)
		s.total[i] = pulseTotals[i];
	return s;
}

String isoTimestampUtc()
{
	time_t now = time(nullptr);
	if (now < 1000000000L)
		return "2026-05-04T12:00:00Z";
	struct tm *timeinfo = gmtime(&now);
	char buffer[25];
	strftime(buffer, sizeof(buffer), "%Y-%m-%dT%H:%M:%SZ", timeinfo);
	return String(buffer);
}

void appendToBacklog(const PulseSample &s, const String &timestamp)
{
	JsonArray arr = configDoc["backlog"].is<JsonArray>()
						? configDoc["backlog"].as<JsonArray>()
						: configDoc.createNestedArray("backlog");
	while (arr.size() >= MAX_BACKLOG_SAMPLES)
		arr.remove(0);
	JsonObject entry = arr.createNestedObject();
	entry["timestamp"] = timestamp;
	JsonObject totals = entry.createNestedObject("pulses_total");
	totals["d1"] = s.total[0];
	totals["d2"] = s.total[1];
	totals["d3"] = s.total[2];
	totals["d4"] = s.total[3];
	configSave();
	Serial.printf("Minta pufferelve, backlog meret: %d\n", arr.size());
}

void loadPreferredNetworks()
{
	preferredNetworkCount = 0;
	if (!configDoc["pref_nets"].is<JsonArray>())
		return;
	for (JsonObject item : configDoc["pref_nets"].as<JsonArray>())
	{
		if (preferredNetworkCount >= MAX_PREFERRED_NETWORKS)
			break;
		const char *ssid = item["ssid"];
		const char *pass = item["password"];
		if (!ssid || strlen(ssid) == 0)
			continue;
		preferredNetworks[preferredNetworkCount].ssid = String(ssid);
		preferredNetworks[preferredNetworkCount].password = pass ? String(pass) : String("");
		preferredNetworkCount++;
	}
	Serial.printf("Betoltott preferalt halozatok szama: %d\n", preferredNetworkCount);
}

bool savePreferredNetworksIfChanged(JsonArray networks)
{
	String incoming;
	serializeJson(networks, incoming);
	String current;
	if (configDoc["pref_nets"].is<JsonArray>())
		serializeJson(configDoc["pref_nets"], current);
	else
		current = "[]";
	if (incoming == current)
		return false;
	configDoc["pref_nets"] = networks;
	configSave();
	Serial.println("Preferalt WiFi lista frissitve a szerver valasza alapjan.");
	loadPreferredNetworks();
	return true;
}

bool saveRuntimeProvisioningIfChanged(JsonObject provisioning)
{
	const char *deviceId = provisioning["device_id"];
	const char *apiUser = provisioning["api_user"];
	const char *apiPass = provisioning["api_pass"];
	const char *apiKey = provisioning["api_key"];
	const char *otaPass = provisioning["ota_pass"];
	if (!deviceId || !apiUser || !apiPass || !apiKey || !otaPass)
		return false;

	bool changed = false;
	if (runtimeDeviceId != deviceId)
	{
		configPutString("device_id", deviceId);
		changed = true;
	}
	if (runtimeApiBasicAuthUser != apiUser)
	{
		configPutString("api_user", apiUser);
		changed = true;
	}
	if (runtimeApiBasicAuthPass != apiPass)
	{
		configPutString("api_pass", apiPass);
		changed = true;
	}
	if (runtimeApiKey != apiKey)
	{
		configPutString("api_key", apiKey);
		changed = true;
	}
	if (runtimeOtaPassword != otaPass)
	{
		configPutString("ota_pass", otaPass);
		changed = true;
	}
	if (changed)
		Serial.println("Online provisioning elmentve.");
	return changed;
}

void scanWifiNetworks()
{
	wifiScanResultCount = 0;
	WifiObservation allNetworks[MAX_WIFI_SCAN_NETWORKS];
	int allNetworkCount = 0;
	int found = -1;
	for (int attempt = 1; attempt <= 3 && found <= 0; attempt++)
	{
		feedWatchdog();
		WiFi.scanDelete();
		if (attempt > 1)
			delay(500);
		found = WiFi.scanNetworks(false, true);
		Serial.printf("WiFi scan probalkozas %d: %d talalat\n", attempt, found);
	}
	if (found <= 0)
	{
		wifiScanPending = true;
		Serial.println("WiFi scan nem talalt halozatot.");
		return;
	}
	Serial.println("Teljes nyers WiFi scan:");
	for (int i = 0; i < found; i++)
		Serial.printf("  raw %d: %s, RSSI=%d, channel=%d\n", i + 1, WiFi.SSID(i).c_str(), WiFi.RSSI(i), WiFi.channel(i));
	for (int i = 0; i < found && allNetworkCount < MAX_WIFI_SCAN_NETWORKS; i++)
	{
		String ssid = WiFi.SSID(i);
		if (ssid.isEmpty())
			continue;
		bool duplicate = false;
		for (int j = 0; j < allNetworkCount; j++)
		{
			if (allNetworks[j].ssid == ssid)
			{
				if (WiFi.RSSI(i) > allNetworks[j].rssi)
					allNetworks[j].rssi = WiFi.RSSI(i);
				duplicate = true;
				break;
			}
		}
		if (duplicate)
			continue;
		allNetworks[allNetworkCount].ssid = ssid;
		allNetworks[allNetworkCount].rssi = WiFi.RSSI(i);
		allNetworkCount++;
	}
	for (int i = 0; i < allNetworkCount - 1; i++)
		for (int j = i + 1; j < allNetworkCount; j++)
			if (allNetworks[j].rssi > allNetworks[i].rssi)
			{
				WifiObservation temp = allNetworks[i];
				allNetworks[i] = allNetworks[j];
				allNetworks[j] = temp;
			}
	wifiScanResultCount = min(allNetworkCount, MAX_WIFI_SCAN_RESULTS);
	for (int i = 0; i < wifiScanResultCount; i++)
		wifiScanResults[i] = allNetworks[i];
	WiFi.scanDelete();
	wifiScanPending = true;
	Serial.printf("WiFi felderites: %d SSID\n", wifiScanResultCount);
}

bool tryConnect(const char *ssid, const char *password, unsigned long timeoutMs)
{
	Serial.printf("Csatlakozas: %s\n", ssid);
	WiFi.disconnect(false);
	delay(100);
	WiFi.begin(ssid, password);
	unsigned long start = millis();
	while (WiFi.status() != WL_CONNECTED && millis() - start < timeoutMs)
	{
		feedWatchdog();
		delay(250);
		Serial.print('.');
	}
	Serial.println();
	wl_status_t status = WiFi.status();
	Serial.printf("WiFi allapot: %d (%s)\n", static_cast<int>(status),
				  status == WL_NO_SSID_AVAIL ? "SSID nem erheto el" :
				  status == WL_CONNECT_FAILED ? "kapcsolodas sikertelen, jelszo vagy WPA mod" :
				  status == WL_CONNECTION_LOST ? "kapcsolat elveszett" : "ismeretlen");
	return status == WL_CONNECTED;
}

void connectWifi()
{
	initializeWifiRadio();
	if (wifiScanResultCount == 0)
		scanWifiNetworks();

	if (preferredNetworkCount > 0)
	{
		for (int i = 0; i < preferredNetworkCount; i++)
		{
			const WifiCandidate &net = preferredNetworks[i];
			if (tryConnect(net.ssid.c_str(), net.password.c_str(), 10000))
			{
				Serial.print("Csatlakozva a preferalt halozathoz, IP: ");
				Serial.println(WiFi.localIP());
				return;
			}
		}
		Serial.println("Visszaeses az alapertelmezett hotspotra.");
	}

	bool hotspotVisible = false;
	for (int i = 0; i < wifiScanResultCount; i++)
	{
		if (wifiScanResults[i].ssid == runtimeHotspotSsid)
		{
			hotspotVisible = true;
			break;
		}
	}
	if (!hotspotVisible)
		Serial.printf("FIGYELEM: a fallback SSID nem lathato a scanben: %s\n", runtimeHotspotSsid.c_str());

	if (tryConnect(runtimeHotspotSsid.c_str(), runtimeHotspotPass.c_str(), 20000))
	{
		Serial.print("Csatlakozva az alapertelmezett hotspothoz, IP: ");
		Serial.println(WiFi.localIP());
	}
	else
		Serial.println("Nem sikerult csatlakozni sem a preferalt halozatokhoz, sem a hotspothoz.");
}

// OTA rollback-safety KOZELITES (nincs natív A/B-partícios megfelelo
// ESP8266-on): egy fuggoben levo celverziot 3 sikertelen (a celverziot meg
// nem igazolo) boot utan 24 orara "blokkoltnak" jelolunk, hogy ne
// probalkozzon vegtelenul ugyanazzal a hibas binarissal. A tenyleges
// binarist nem tudjuk visszaallitani -- ez csak a vegtelen
// flash-crash-reboot ciklus ellen ved, dokumentaltan gyengebb garancia,
// mint az ESP32 oldalon.
void checkPendingFirmwareConfirmation()
{
	String pendingVersion = configGetString("ota_pending_version", "");
	if (pendingVersion.isEmpty())
		return;
	if (pendingVersion == FIRMWARE_VERSION)
		return; // postSample() fogja megerositeni az elso sikeres push utan

	int boots = configGetString("ota_pending_boots", "0").toInt() + 1;
	Serial.printf("Fuggoben levo frissites (%s) meg nem igazolt, %d. boot ezen a verzion.\n",
				  pendingVersion.c_str(), boots);
	if (boots >= 3)
	{
		Serial.println("Harom sikertelen boot ugyanarra a celverziora -- 24 orara felfuggesztve.");
		configPutString("ota_blocked_version", pendingVersion);
		configPutString("ota_blocked_until", String((unsigned long)(time(nullptr) + 86400UL)));
		configRemove("ota_pending_version");
		configRemove("ota_pending_boots");
	}
	else
	{
		configPutString("ota_pending_boots", String(boots));
	}
}

void applyFirmwareUpdate(const String &url)
{
	Serial.printf("Firmware letoltes inditasa: %s\n", url.c_str());
	BearSSL::WiFiClientSecure client;
	BearSSL::X509List caCert(FIRMWARE_CA_CERT);
	client.setTrustAnchors(&caCert);
	client.setBufferSizes(1024, 1024);

	ESPhttpUpdate.rebootOnUpdate(true);
	ESPhttpUpdate.onProgress([](int done, int total)
							  { feedWatchdog(); });

	// Az ESP8266 HTTPUpdate API-ja nem ad kenyelmes header/basic-auth
	// callback-et, ezert az X-API-KEY-t URL query parameterkent kuldjuk --
	// a szerver oldali letoltesi endpointnak ESP8266-os eszkozoknel EZT IS
	// el kell fogadnia (nem csak az X-API-KEY headert, amit a /push hasznal).
	String fullUrl = url;
	fullUrl += (url.indexOf('?') >= 0) ? "&" : "?";
	fullUrl += "api_key=" + runtimeApiKey;

	configPutString("ota_pending_version", lastFirmwareAttemptVersion);
	configPutString("ota_pending_boots", "0");

	t_httpUpdate_return result = ESPhttpUpdate.update(client, fullUrl, FIRMWARE_VERSION);

	switch (result)
	{
	case HTTP_UPDATE_FAILED:
		Serial.printf("Firmware frissites sikertelen (%d): %s\n",
					  ESPhttpUpdate.getLastError(), ESPhttpUpdate.getLastErrorString().c_str());
		break;
	case HTTP_UPDATE_NO_UPDATES:
		Serial.println("Firmware frissites: a szerver szerint nincs ujabb verzio.");
		break;
	case HTTP_UPDATE_OK:
		Serial.println("Firmware frissites sikeres, ujrainditas...");
		break;
	}
}

void applyOneShotCommands(JsonArray commands)
{
	for (JsonObject cmd : commands)
	{
		const char *type = cmd["cmd"];
		if (!type)
			continue;
		if (strcmp(type, "reboot") == 0)
		{
			Serial.println("Parancs: reboot.");
			restartRequested = true;
		}
		else if (strcmp(type, "factory_reset") == 0)
		{
			Serial.println("Parancs: factory_reset -- config torlese, ujra-enrollment lesz szukseges.");
			configDoc.clear();
			configSave();
			restartRequested = true;
		}
		else
		{
			Serial.printf("Ismeretlen parancs figyelmen kivul hagyva: %s\n", type);
		}
	}
}

bool postSample(const PulseSample &s)
{
	if (WiFi.status() != WL_CONNECTED)
		return false;
	if (runtimeDeviceId.isEmpty() || runtimeApiKey.isEmpty() ||
		runtimeApiBasicAuthUser.isEmpty() || runtimeApiBasicAuthPass.isEmpty())
	{
		Serial.println("POST kihagyva: hianyos API konfiguracio.");
		return false;
	}

	Serial.printf("CHECKPOINT A, heap=%u\n", ESP.getFreeHeap());
	String timestamp = isoTimestampUtc();
	StaticJsonDocument<1536> doc;
	doc["device_id"] = runtimeDeviceId;
	doc["timestamp"] = timestamp;
	doc["heartbeat"] = true;
	doc["uptime_seconds"] = millis() / 1000;
	doc["ota_enabled"] = otaStarted;
	doc["firmware_version"] = FIRMWARE_VERSION;
	doc["platform"] = "esp8266";
	doc["reset_reason"] = resetReasonString();
	JsonObject wifi = doc.createNestedObject("wifi");
	wifi["ssid"] = WiFi.SSID();
	wifi["rssi"] = WiFi.RSSI();
	wifi["ip"] = WiFi.localIP().toString();
	Serial.printf("CHECKPOINT B, heap=%u\n", ESP.getFreeHeap());
	if (wifiScanPending)
	{
		JsonArray scan = doc.createNestedArray("wifi_scan");
		for (int i = 0; i < wifiScanResultCount; i++)
		{
			JsonObject network = scan.createNestedObject();
			network["ssid"] = wifiScanResults[i].ssid;
			network["rssi"] = wifiScanResults[i].rssi;
		}
	}
	Serial.printf("CHECKPOINT C, heap=%u\n", ESP.getFreeHeap());
	JsonObject totals = doc.createNestedObject("pulses_total");
	totals["d1"] = s.total[0];
	totals["d2"] = s.total[1];
	totals["d3"] = s.total[2];
	totals["d4"] = s.total[3];

	if (configDoc["backlog"].is<JsonArray>() && configDoc["backlog"].as<JsonArray>().size() > 0)
		doc["backlog"] = configDoc["backlog"];

	Serial.printf("CHECKPOINT D, heap=%u, overflowed=%d, doc_len=%u\n", ESP.getFreeHeap(), doc.overflowed(), measureJson(doc));
	String payload;
	payload.reserve(measureJson(doc) + 16);
	serializeJson(doc, payload);
	Serial.printf("CHECKPOINT E, heap=%u, payload_len=%u\n", ESP.getFreeHeap(), payload.length());

	BearSSL::WiFiClientSecure client;
	client.setInsecure();
	client.setBufferSizes(2048, 2048);
	Serial.printf("CHECKPOINT F, heap=%u\n", ESP.getFreeHeap());
	HTTPClient http;
	http.begin(client, runtimeApiUrl);
	Serial.printf("CHECKPOINT G, heap=%u\n", ESP.getFreeHeap());
	http.addHeader("Content-Type", "application/json");
	http.addHeader("X-API-KEY", runtimeApiKey.c_str());
	http.setAuthorization(runtimeApiBasicAuthUser.c_str(), runtimeApiBasicAuthPass.c_str());
	Serial.printf("CHECKPOINT H, heap=%u\n", ESP.getFreeHeap());

	int code = http.POST(payload);
	Serial.printf("CHECKPOINT I, heap=%u, code=%d\n", ESP.getFreeHeap(), code);
	String response = http.getString();
	http.end();
	Serial.printf("POST code=%d response=%s\n", code, response.c_str());
	recordApiAuthenticationResult(code);

	if (code < 200 || code >= 300)
	{
		appendToBacklog(s, timestamp);
		return false;
	}
	wifiScanPending = false;
	if (configIsKey("backlog"))
		configRemove("backlog");

	if (!firmwareValidated)
	{
		firmwareValidated = true;
		if (configGetString("ota_pending_version", "") == FIRMWARE_VERSION)
		{
			configRemove("ota_pending_version");
			configRemove("ota_pending_boots");
			Serial.println("Firmware-frissites megerositve (elso sikeres push az uj verzion).");
		}
	}

	StaticJsonDocument<1536> respDoc;
	if (deserializeJson(respDoc, response) == DeserializationError::Ok)
	{
		if (respDoc["provisioning"].is<JsonObject>() &&
			saveRuntimeProvisioningIfChanged(respDoc["provisioning"].as<JsonObject>()))
			restartRequested = true;

		if (respDoc["wifi_networks"].is<JsonArray>() &&
			savePreferredNetworksIfChanged(respDoc["wifi_networks"].as<JsonArray>()))
			restartRequested = true;

		bool remoteRestart = respDoc["reboot"] | false;
		remoteRestart = remoteRestart || (respDoc["restart"] | false);
		if (remoteRestart)
		{
			Serial.println("Tavoli ujrainditas kerve a szerver valaszaban.");
			restartRequested = true;
		}

		if (respDoc["commands"].is<JsonArray>())
			applyOneShotCommands(respDoc["commands"].as<JsonArray>());

		if (respDoc["firmware"].is<JsonObject>())
		{
			JsonObject firmware = respDoc["firmware"].as<JsonObject>();
			const char *targetVersion = firmware["version"];
			const char *firmwareUrl = firmware["url"];
			if (targetVersion && firmwareUrl && strlen(firmwareUrl) > 0 &&
				strcmp(targetVersion, FIRMWARE_VERSION) != 0)
			{
				String blockedVersion = configGetString("ota_blocked_version", "");
				unsigned long blockedUntil = (unsigned long)configGetString("ota_blocked_until", "0").toInt();
				bool isBlocked = !blockedVersion.isEmpty() && blockedVersion == String(targetVersion) &&
								 (unsigned long)time(nullptr) < blockedUntil;

				if (isBlocked)
				{
					Serial.printf("Firmware celverzio (%s) 24 oras felfuggesztes alatt, kihagyva.\n", targetVersion);
				}
				else
				{
					if (lastFirmwareAttemptVersion != targetVersion)
					{
						firmwareAttemptCount = 0;
						lastFirmwareAttemptMs = 0;
						lastFirmwareAttemptVersion = targetVersion;
					}
					unsigned long backoffMs = FIRMWARE_RETRY_BASE_MS * (1UL << min(firmwareAttemptCount, 5u));
					if (backoffMs > FIRMWARE_RETRY_MAX_MS)
						backoffMs = FIRMWARE_RETRY_MAX_MS;
					unsigned long nowMs = millis();

					if (lastFirmwareAttemptMs != 0 && nowMs - lastFirmwareAttemptMs < backoffMs)
						Serial.printf("Firmware frissites (%s) kihagyva, meg %lu s varakozas.\n",
									  targetVersion, (backoffMs - (nowMs - lastFirmwareAttemptMs)) / 1000);
					else
					{
						lastFirmwareAttemptMs = nowMs;
						firmwareAttemptCount++;
						Serial.printf("Firmware frissites elerheto: %s -> %s (probalkozas #%u)\n",
									  FIRMWARE_VERSION, targetVersion, firmwareAttemptCount);
						applyFirmwareUpdate(String(firmwareUrl));
					}
				}
			}
		}
	}
	return true;
}

void setup()
{
	Serial.begin(115200);
	delay(200);
	Serial.println("ESP8266 darabszamlalo (gepinfo, atmeneti/kivezetesre szant) indul");
	Serial.printf("Firmware: %s\n", FIRMWARE_VERSION);
	Serial.printf("Elozo ujrainditas oka: %s\n", resetReasonString());
	randomSeed(ESP.getCycleCount());

	wdTicker.attach(1.0, wdTick);

	configLoad();
	initializeConfigDefaults();
	loadRuntimeConfig();
	loadPreferredNetworks();
	initGpioInterruptChannels();
	initializeWifiRadio();
	scanWifiNetworks();
	connectWifi();

	configTime(0, 0, "pool.ntp.org", "time.nist.gov");
	time_t nowSync = time(nullptr);
	unsigned long ntpStart = millis();
	while (nowSync < 1000000000L && millis() - ntpStart < 10000)
	{
		feedWatchdog();
		delay(200);
		nowSync = time(nullptr);
	}
	if (nowSync >= 1000000000L)
		Serial.println("NTP ido szinkronizalva.");
	else
		Serial.println("NTP ido szinkronizalas sikertelen.");

	// Csak NTP-szinkron utan ellenorizzuk a fuggoben levo OTA-t, hogy a
	// 24 oras blokkolasi hatarido helyes epoch-idobelyeget kapjon.
	checkPendingFirmwareConfirmation();

	if (runtimeApiKey.isEmpty() && enrollDevice())
	{
		delay(500);
		ESP.restart();
	}

	Serial.println("DIAGNOSTIC: ArduinoOTA.begin() ideiglenesen kihagyva (postSample() crash izolalasa).");
	if (false && WiFi.status() == WL_CONNECTED && runtimeOtaPassword.length() > 0)
	{
		ArduinoOTA.setHostname(runtimeDeviceId.c_str());
		ArduinoOTA.setPassword(runtimeOtaPassword.c_str());
		ArduinoOTA.onStart([]()
							{ Serial.println("OTA frissites indul."); });
		ArduinoOTA.onEnd([]()
						  { Serial.println("OTA frissites kesz."); });
		ArduinoOTA.onError([](ota_error_t error)
							{ Serial.printf("OTA hiba: %u\n", error); });
		ArduinoOTA.begin();
		otaStarted = true;
		Serial.printf("OTA aktiv: %s.local\n", runtimeDeviceId.c_str());
	}
	else
		Serial.println("OTA kikapcsolva: adj meg OTA_PASSWORD erteket a config_local.h fajlban.");
}

void loop()
{
	feedWatchdog();
	checkWatchdog();
	handleSerialProvisioning();
	if (otaStarted)
		ArduinoOTA.handle();

	if (WiFi.status() != WL_CONNECTED)
		connectWifi();

	const unsigned long nowMs = millis();

	// Az ISR-szamlalokat gyakran atemeljuk a szoftveres osszegbe, hogy egy
	// esetleges tulcsordulastol (bar 32 bites, gyakorlatilag elhanyagolhato)
	// es a race-conditonoktol vedve legyunk.
	static unsigned long lastAccumulateMs = 0;
	if (nowMs - lastAccumulateMs >= 1000 || lastAccumulateMs == 0)
	{
		lastAccumulateMs = nowMs;
		accumulatePulseCounters();
	}

	if (nowMs - lastHeartbeat >= 5000 || lastHeartbeat == 0)
	{
		lastHeartbeat = nowMs;
		Serial.printf("Eletjel: fut, WiFi=%s, uptime=%lu s, d1..d4=%u/%u/%u/%u\n",
					  WiFi.status() == WL_CONNECTED ? "csatlakozva" : "nincs kapcsolat",
					  nowMs / 1000, pulseTotals[0], pulseTotals[1], pulseTotals[2], pulseTotals[3]);
	}

	if (nowMs - lastOnlinePush >= HEARTBEAT_INTERVAL_MS || lastOnlinePush == 0)
	{
		lastOnlinePush = nowMs;
		PulseSample sample = readPulseCounters();
		postSample(sample);
		if (restartRequested)
		{
			Serial.println("Uj beallitasok alkalmazasa, ESP8266 ujraindul.");
			delay(500);
			ESP.restart();
		}
	}
	delay(100);
}
