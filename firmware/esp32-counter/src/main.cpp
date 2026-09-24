#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <ArduinoOTA.h>
#include <HTTPUpdate.h>
#include <esp_wifi.h>
#include <esp_ota_ops.h>
#include <esp_task_wdt.h>
#include <esp_system.h>
#include <driver/pcnt.h>
#include "config_local.h"

// Semantic version, reported on every push and compared against the
// "firmware" object a push response may contain. Bump this whenever the
// server should be able to offer this build as an update target.
const char *FIRMWARE_VERSION = "1.0.0";

// Root CA for the firmware download connection specifically (pinned
// instead of client.setInsecure(), since a MITM'd OTA download means
// arbitrary code execution on the whole fleet, not just bad telemetry).
// This is Let's Encrypt's ISRG Root X2 -- verify it matches the actual
// certificate chain the production server presents before relying on it;
// pinning the root rather than the leaf/intermediate means this doesn't
// need updating when the site's certificate renews every ~90 days.
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

const unsigned long WATCHDOG_TIMEOUT_S = 120;

// ESP32 -> Laravel darabszamlalo adatkuldo (gepinfo /monitor)
//
// WiFi strategia (azonos az Energy projekt mintajaval):
//   - A HOTSPOT_* mindig a beegetett, garantalt tartalek halozat.
//   - A szerver sikeres POST valaszban wifi_networks listat kuldhet.
//   - A halozatok NVS-be kerulnek, majd ujracsatlakozaskor sorrendben probalodnak.
//   - Ha egyik preferalt halozat sem erheto el, a kliens a hotspotra esik vissza.

Preferences prefs;
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

const int MAX_PREFERRED_NETWORKS = 5;
const int MAX_WIFI_SCAN_RESULTS = 5;
const int MAX_WIFI_SCAN_NETWORKS = 64;
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
	wifi_country_t country = {"HU", 1, 13, 84, WIFI_COUNTRY_POLICY_MANUAL};
	esp_wifi_set_country(&country);
	WiFi.mode(WIFI_STA);
	WiFi.setSleep(false);
	Serial.println("WiFi radio inicializalva: HU, 2.4 GHz, 1-13 csatorna.");
}

const char *resetReasonString()
{
	switch (esp_reset_reason())
	{
	case ESP_RST_POWERON:
		return "poweron";
	case ESP_RST_EXT:
		return "ext_reset";
	case ESP_RST_SW:
		return "sw_reset";
	case ESP_RST_PANIC:
		return "panic";
	case ESP_RST_INT_WDT:
		return "int_wdt";
	case ESP_RST_TASK_WDT:
		return "task_wdt";
	case ESP_RST_WDT:
		return "wdt";
	case ESP_RST_DEEPSLEEP:
		return "deepsleep";
	case ESP_RST_BROWNOUT:
		return "brownout";
	case ESP_RST_SDIO:
		return "sdio";
	default:
		return "unknown";
	}
}

String hardwareDeviceId()
{
	uint64_t chipId = ESP.getEfuseMac();
	char buffer[24];
	snprintf(buffer, sizeof(buffer), "ESP32_%llX", static_cast<unsigned long long>(chipId));
	return String(buffer);
}

String loadConfigValue(const char *key, const char *fallback)
{
	String stored;
	if (prefs.isKey(key))
		stored = prefs.getString(key, "");
	if (stored.isEmpty() && fallback != nullptr && strlen(fallback) > 0)
	{
		prefs.putString(key, fallback);
		return String(fallback);
	}
	return stored;
}

void initializeNvsDefaults()
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
		if (!prefs.isKey(item.key) && item.value != nullptr && strlen(item.value) > 0)
		{
			prefs.putString(item.key, item.value);
			initialized = true;
		}
	}

	if (initialized)
		Serial.println("NVS alapkonfiguracio inicializalva.");
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
	apiAuthFailureCount = prefs.getUInt("api_401_count", 0);
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
	StaticJsonDocument<256> requestDoc;
	requestDoc["device_id"] = runtimeDeviceId;
	String payload;
	serializeJson(requestDoc, payload);

	WiFiClientSecure client;
	client.setInsecure();
	HTTPClient http;
	if (!http.begin(client, enrollmentUrl.c_str()))
		return false;
	http.addHeader("Content-Type", "application/json");
	http.setAuthorization(runtimeApiBasicAuthUser.c_str(), runtimeApiBasicAuthPass.c_str());
	int code = http.POST(payload);
	String response = http.getString();
	http.end();
	Serial.printf("Enrollment code=%d\n", code);

	if (code == 202)
	{
		// Ismeretlen/meg nem jovahagyott eszkoz -- a szerver varolistara tette
		// (PendingDevice), admin joevahagyasra var. Ujra probalkozunk kesobb.
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

	prefs.putString("api_key", apiKey);
	prefs.putString("ota_pass", otaPass);
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
		prefs.putUInt("api_401_count", apiAuthFailureCount);
		Serial.printf("API hitelesitesi hiba: %u/%u\n", apiAuthFailureCount, MAX_API_AUTH_FAILURES);

		if (apiAuthFailureCount >= MAX_API_AUTH_FAILURES)
		{
			prefs.remove("api_key");
			runtimeApiKey = "";
			apiAuthFailureCount = 0;
			prefs.putUInt("api_401_count", 0);
			Serial.println("Az API kulcs 10 egymast koveto 401 hiba utan torolve. Uj provisioning szukseges.");
		}
		return;
	}

	if (httpCode >= 200 && httpCode < 300)
	{
		apiAuthFailureCount = 0;
		prefs.putUInt("api_401_count", 0);
	}
}

void saveRuntimeConfigValue(const String &key, const String &value)
{
	if (key == "hotspot_ssid" || key == "hotspot_pass")
	{
		Serial.println("Az alapertelmezett hotspot forraskodban rogzitett, nem modosithato.");
		return;
	}
	if (key == "api_url")
		prefs.putString("api_url", value);
	else if (key == "api_user")
		prefs.putString("api_user", value);
	else if (key == "api_pass")
		prefs.putString("api_pass", value);
	else if (key == "device_id")
		prefs.putString("device_id", value);
	else if (key == "api_key")
		prefs.putString("api_key", value);
	else if (key == "ota_pass")
		prefs.putString("ota_pass", value);
	else
	{
		Serial.println("Ismeretlen konfiguracios kulcs.");
		return;
	}

	loadRuntimeConfig();
	Serial.println("Konfiguracio elmentve. Az uj ertekekhez inditsd ujra az ESP32-t.");
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

// --- Impulzus-szamlalas (ESP32 hardveres PCNT periferia) ----------------
// 4 fuggetlen csatorna (d1..d4), egyenkent egy PCNT egyseggel. A PCNT
// hardver-regiszter elojeles 16 bites, ezert masodpercenkent kiolvassuk es
// nullazzuk, majd szoftveresen egy monoton 32 bites osszegbe gyujtjuk --
// igy a push-ciklus (60s) alatt sem csordulhat tul realisztikus
// impulzusszam mellett.

const int PULSE_CHANNEL_COUNT = 4;
const int PULSE_GPIOS[PULSE_CHANNEL_COUNT] = {PULSE_GPIO_D1, PULSE_GPIO_D2, PULSE_GPIO_D3, PULSE_GPIO_D4};
const pcnt_unit_t PULSE_PCNT_UNITS[PULSE_CHANNEL_COUNT] = {PCNT_UNIT_0, PCNT_UNIT_1, PCNT_UNIT_2, PCNT_UNIT_3};

uint32_t pulseTotals[PULSE_CHANNEL_COUNT] = {0, 0, 0, 0};
unsigned long lastPulseAccumulateMs = 0;
const unsigned long PULSE_ACCUMULATE_INTERVAL_MS = 1000;

void initPcntChannels()
{
	for (int i = 0; i < PULSE_CHANNEL_COUNT; i++)
	{
		pinMode(PULSE_GPIOS[i], PULSE_ACTIVE_LOW ? INPUT_PULLUP : INPUT_PULLDOWN);

		pcnt_config_t cfg = {};
		cfg.pulse_gpio_num = PULSE_GPIOS[i];
		cfg.ctrl_gpio_num = PCNT_PIN_NOT_USED;
		cfg.channel = PCNT_CHANNEL_0;
		cfg.unit = PULSE_PCNT_UNITS[i];
		// NPN (active-low): a jel a FALLING elen valt aktivva -> ott szamolunk.
		// PNP (active-high): a jel a RISING elen valt aktivva -> ott szamolunk.
		cfg.pos_mode = PULSE_ACTIVE_LOW ? PCNT_COUNT_DIS : PCNT_COUNT_INC;
		cfg.neg_mode = PULSE_ACTIVE_LOW ? PCNT_COUNT_INC : PCNT_COUNT_DIS;
		cfg.lctrl_mode = PCNT_MODE_KEEP;
		cfg.hctrl_mode = PCNT_MODE_KEEP;
		cfg.counter_h_lim = 30000;
		cfg.counter_l_lim = 0;
		pcnt_unit_config(&cfg);

		pcnt_set_filter_value(PULSE_PCNT_UNITS[i], PULSE_DEBOUNCE_FILTER_CYCLES);
		pcnt_filter_enable(PULSE_PCNT_UNITS[i]);

		pcnt_counter_pause(PULSE_PCNT_UNITS[i]);
		pcnt_counter_clear(PULSE_PCNT_UNITS[i]);
		pcnt_counter_resume(PULSE_PCNT_UNITS[i]);
	}
	Serial.println("PCNT impulzusszamlalo csatornak inicializalva.");
}

// Kiolvassa es nullazza mind a 4 PCNT egyseget, a nyers erteket a szoftveres
// osszegbe adva. Gyakran hivando (lasd PULSE_ACCUMULATE_INTERVAL_MS), hogy a
// 16 bites hardver-regiszter sose tudjon realisztikus impulzusszam mellett
// tulcsordulni a ket kiolvasas kozott.
void accumulatePulseCounters()
{
	for (int i = 0; i < PULSE_CHANNEL_COUNT; i++)
	{
		int16_t raw = 0;
		pcnt_get_counter_value(PULSE_PCNT_UNITS[i], &raw);
		pcnt_counter_pause(PULSE_PCNT_UNITS[i]);
		pcnt_counter_clear(PULSE_PCNT_UNITS[i]);
		pcnt_counter_resume(PULSE_PCNT_UNITS[i]);
		if (raw > 0)
			pulseTotals[i] += static_cast<uint32_t>(raw);
	}
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
	struct tm timeinfo;
	if (!getLocalTime(&timeinfo))
		return "2026-05-04T12:00:00Z";

	char buffer[25];
	strftime(buffer, sizeof(buffer), "%Y-%m-%dT%H:%M:%SZ", &timeinfo);
	return String(buffer);
}

void appendToBacklog(const PulseSample &s, const String &timestamp)
{
	StaticJsonDocument<1024> doc;
	String existing = prefs.isKey("backlog") ? prefs.getString("backlog", "[]") : "[]";
	deserializeJson(doc, existing);
	JsonArray arr = doc.as<JsonArray>();
	if (arr.isNull())
		arr = doc.to<JsonArray>();
	while (arr.size() >= MAX_BACKLOG_SAMPLES)
		arr.remove(0);

	JsonObject entry = arr.createNestedObject();
	entry["timestamp"] = timestamp;
	JsonObject totals = entry.createNestedObject("pulses_total");
	totals["d1"] = s.total[0];
	totals["d2"] = s.total[1];
	totals["d3"] = s.total[2];
	totals["d4"] = s.total[3];

	String out;
	serializeJson(doc, out);
	prefs.putString("backlog", out);
	Serial.printf("Minta pufferelve, backlog meret: %d\n", arr.size());
}

void loadPreferredNetworks()
{
	preferredNetworkCount = 0;
	String json = prefs.getString("pref_nets", "[]");
	StaticJsonDocument<1024> doc;
	if (deserializeJson(doc, json) != DeserializationError::Ok)
		return;

	for (JsonObject item : doc.as<JsonArray>())
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
	String current = prefs.getString("pref_nets", "[]");
	if (incoming == current)
		return false;

	prefs.putString("pref_nets", incoming);
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
		prefs.putString("device_id", deviceId);
		changed = true;
	}
	if (runtimeApiBasicAuthUser != apiUser)
	{
		prefs.putString("api_user", apiUser);
		changed = true;
	}
	if (runtimeApiBasicAuthPass != apiPass)
	{
		prefs.putString("api_pass", apiPass);
		changed = true;
	}
	if (runtimeApiKey != apiKey)
	{
		prefs.putString("api_key", apiKey);
		changed = true;
	}
	if (runtimeOtaPassword != otaPass)
	{
		prefs.putString("ota_pass", otaPass);
		changed = true;
	}

	if (changed)
		Serial.println("Online provisioning elmentve az NVS-be.");
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
		esp_task_wdt_reset();
		WiFi.scanDelete();
		if (attempt > 1)
			delay(500);
		found = WiFi.scanNetworks(false, true, false, 300, 0);
		Serial.printf("WiFi scan probalkozas %d: %d talalat\n", attempt, found);
	}
	if (found <= 0)
	{
		wifiScanPending = true;
		Serial.println("WiFi scan nem talalt halozatot.");
		return;
	}
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
	for (int i = 0; i < wifiScanResultCount; i++)
		Serial.printf("  %d. %s (%d dBm)\n", i + 1, wifiScanResults[i].ssid.c_str(), wifiScanResults[i].rssi);
}

bool tryConnect(const char *ssid, const char *password, unsigned long timeoutMs)
{
	Serial.printf("Csatlakozas: %s\n", ssid);
	WiFi.disconnect(false, false);
	delay(100);
	WiFi.setSleep(false);
	WiFi.begin(ssid, password);
	unsigned long start = millis();
	while (WiFi.status() != WL_CONNECTED && millis() - start < timeoutMs)
	{
		esp_task_wdt_reset();
		delay(250);
		Serial.print('.');
	}
	Serial.println();
	return WiFi.status() == WL_CONNECTED;
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

	if (tryConnect(runtimeHotspotSsid.c_str(), runtimeHotspotPass.c_str(), 20000))
	{
		Serial.print("Csatlakozva az alapertelmezett hotspothoz (SSID: ");
		Serial.print(WiFi.SSID());
		Serial.print("), IP: ");
		Serial.println(WiFi.localIP());
	}
	else
		Serial.println("Nem sikerult csatlakozni sem a preferalt halozatokhoz, sem a hotspothoz.");
}

void applyFirmwareUpdate(const char *url)
{
	Serial.printf("Firmware letoltes inditasa: %s\n", url);
	WiFiClientSecure client;
	// Pinned CA, not setInsecure(): this connection can flash arbitrary
	// code onto the device, so it gets real certificate verification
	// unlike the rest of the app's telemetry-only connections.
	client.setCACert(FIRMWARE_CA_CERT);
	httpUpdate.rebootOnUpdate(true);
	httpUpdate.onProgress([](int done, int total)
						   { esp_task_wdt_reset(); });

	String authUser = runtimeApiBasicAuthUser;
	String authPass = runtimeApiBasicAuthPass;
	String apiKey = runtimeApiKey;
	t_httpUpdate_return result = httpUpdate.update(client, url, FIRMWARE_VERSION,
		[authUser, authPass, apiKey](HTTPClient *http)
		{
			http->setAuthorization(authUser.c_str(), authPass.c_str());
			http->addHeader("X-API-KEY", apiKey.c_str());
		});

	switch (result)
	{
	case HTTP_UPDATE_FAILED:
		Serial.printf("Firmware frissites sikertelen (%d): %s\n",
					  httpUpdate.getLastError(), httpUpdate.getLastErrorString().c_str());
		break;
	case HTTP_UPDATE_NO_UPDATES:
		Serial.println("Firmware frissites: a szerver szerint nincs ujabb verzio.");
		break;
	case HTTP_UPDATE_OK:
		Serial.println("Firmware frissites sikeres, ujrainditas...");
		break;
	}
}

// Egyszeri (one-shot) parancsok a push valaszban -- a szerver mar kezbesitve
// (delivered) jeloli oket a valasz megepitese elott, ezert itt nincs kulon
// visszaigazolas (ack): csak vegre kell hajtani, amit kapunk.
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
			Serial.println("Parancs: factory_reset -- NVS torlese, ujra-enrollment lesz szukseges.");
			prefs.clear();
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

	String timestamp = isoTimestampUtc();
	StaticJsonDocument<2048> doc;
	doc["device_id"] = runtimeDeviceId;
	doc["timestamp"] = timestamp;
	doc["heartbeat"] = true;
	doc["uptime_seconds"] = millis() / 1000;
	doc["ota_enabled"] = otaStarted;
	doc["firmware_version"] = FIRMWARE_VERSION;
	doc["platform"] = "esp32";
	doc["reset_reason"] = resetReasonString();
	JsonObject wifi = doc.createNestedObject("wifi");
	wifi["ssid"] = WiFi.SSID();
	wifi["rssi"] = WiFi.RSSI();
	wifi["ip"] = WiFi.localIP().toString();
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
	JsonObject totals = doc.createNestedObject("pulses_total");
	totals["d1"] = s.total[0];
	totals["d2"] = s.total[1];
	totals["d3"] = s.total[2];
	totals["d4"] = s.total[3];

	String backlogJson = prefs.isKey("backlog") ? prefs.getString("backlog", "[]") : "[]";
	if (backlogJson != "[]")
	{
		StaticJsonDocument<1024> backlogDoc;
		if (deserializeJson(backlogDoc, backlogJson) == DeserializationError::Ok)
			doc["backlog"] = backlogDoc.as<JsonArray>();
	}

	String payload;
	serializeJson(doc, payload);
	WiFiClientSecure client;
	client.setInsecure();
	HTTPClient http;
	http.begin(client, runtimeApiUrl.c_str());
	http.addHeader("Content-Type", "application/json");
	http.addHeader("X-API-KEY", runtimeApiKey.c_str());
	http.setAuthorization(runtimeApiBasicAuthUser.c_str(), runtimeApiBasicAuthPass.c_str());

	int code = http.POST(payload);
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
	if (prefs.isKey("backlog"))
		prefs.remove("backlog");

	if (!firmwareValidated)
	{
		esp_ota_mark_app_valid_cancel_rollback();
		firmwareValidated = true;
		Serial.println("Firmware ervenyesnek jelolve (OTA rollback torolve).");
	}

	StaticJsonDocument<2048> respDoc;
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
					applyFirmwareUpdate(firmwareUrl);
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
	Serial.println("ESP32 darabszamlalo (gepinfo) indul");
	Serial.printf("Firmware: %s\n", FIRMWARE_VERSION);
	Serial.printf("Elozo ujrainditas oka: %s\n", resetReasonString());
	randomSeed(esp_random());
	esp_task_wdt_init(WATCHDOG_TIMEOUT_S, true);
	esp_task_wdt_add(NULL);
	prefs.begin("pulsecnt", false);
	initializeNvsDefaults();
	loadRuntimeConfig();
	loadPreferredNetworks();
	initPcntChannels();
	initializeWifiRadio();
	scanWifiNetworks();
	connectWifi();
	if (runtimeApiKey.isEmpty() && enrollDevice())
	{
		delay(500);
		ESP.restart();
	}
	configTime(0, 0, "pool.ntp.org", "time.nist.gov");
	struct tm timeinfo;
	if (WiFi.status() == WL_CONNECTED && getLocalTime(&timeinfo, 10000))
		Serial.println("NTP ido szinkronizalva.");
	else
		Serial.println("NTP ido szinkronizalas sikertelen.");

	if (WiFi.status() == WL_CONNECTED && runtimeOtaPassword.length() > 0)
	{
		ArduinoOTA.setHostname(runtimeDeviceId.c_str());
		ArduinoOTA.setPassword(runtimeOtaPassword.c_str());
		ArduinoOTA.onStart([]() { Serial.println("OTA frissites indul."); });
		ArduinoOTA.onEnd([]() { Serial.println("OTA frissites kesz."); });
		ArduinoOTA.onError([](ota_error_t error) { Serial.printf("OTA hiba: %u\n", error); });
		ArduinoOTA.begin();
		otaStarted = true;
		Serial.printf("OTA aktiv: %s.local\n", runtimeDeviceId.c_str());
	}
	else
		Serial.println("OTA kikapcsolva: adj meg OTA_PASSWORD erteket a config_local.h fajlban.");
}

void loop()
{
	esp_task_wdt_reset();
	handleSerialProvisioning();
	if (otaStarted)
		ArduinoOTA.handle();

	if (WiFi.status() != WL_CONNECTED)
		connectWifi();

	const unsigned long nowMs = millis();

	if (nowMs - lastPulseAccumulateMs >= PULSE_ACCUMULATE_INTERVAL_MS || lastPulseAccumulateMs == 0)
	{
		lastPulseAccumulateMs = nowMs;
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
			Serial.println("Uj beallitasok alkalmazasa, ESP32 ujraindul.");
			delay(500);
			ESP.restart();
		}
	}
	delay(500);
}
