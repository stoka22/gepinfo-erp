<x-filament-panels::page>
    @if ($errors->any())
        <div class="fw-errors">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.firmware.upload') }}" enctype="multipart/form-data" class="fw-form">
        @csrf

        <div class="fw-grid">
            <div>
                <label class="fw-label" for="device_id">Eszköz (opcionális)</label>
                <select name="device_id" id="device_id" class="fw-input">
                    <option value="">— Univerzális / hardverkód alapján —</option>
                    @foreach ($this->getDevices() as $device)
                        <option value="{{ $device->id }}" @selected(old('device_id') == $device->id)>{{ $device->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="fw-label" for="platform">Platform<span class="fw-required">*</span></label>
                <select name="platform" id="platform" class="fw-input" required>
                    <option value="esp32" @selected(old('platform', 'esp32') === 'esp32')>ESP32</option>
                    <option value="esp8266" @selected(old('platform') === 'esp8266')>ESP8266</option>
                </select>
                <p class="fw-help">A push-válasz csak a device.platform-mal EGYEZŐ firmware-t ajánlja fel.</p>
            </div>

            <div>
                <label class="fw-label" for="hardware_code">Hardverkód</label>
                <input type="text" name="hardware_code" id="hardware_code" class="fw-input" placeholder="pl. ESP32-WROOM-32E" value="{{ old('hardware_code') }}">
            </div>

            <div>
                <label class="fw-label" for="version">Verzió<span class="fw-required">*</span></label>
                <input type="text" name="version" id="version" class="fw-input" placeholder="1.2.3" value="{{ old('version') }}" required>
            </div>

            <div>
                <label class="fw-label" for="build">Build</label>
                <input type="number" name="build" id="build" class="fw-input" min="1" value="{{ old('build', 1) }}">
            </div>

            <div>
                <label class="fw-label" for="firmware">Firmware fájl<span class="fw-required">*</span></label>
                <input type="file" name="firmware" id="firmware" class="fw-input" required>
                <p class="fw-help">.bin / .uf2 / .zip, max. 100 MB.</p>
            </div>
        </div>

        <div class="fw-checkbox-row">
            <input type="checkbox" name="forced" id="forced" value="1" @checked(old('forced'))>
            <label for="forced">Kötelező frissítés</label>
        </div>

        <div>
            <label class="fw-label" for="notes">Megjegyzés</label>
            <textarea name="notes" id="notes" class="fw-input" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div class="fw-actions">
            <button type="submit" class="fw-btn">Feltöltés</button>
            <a href="{{ \App\Filament\Resources\FirmwareResource::getUrl('index') }}" class="fw-btn fw-btn-secondary">Mégse</a>
        </div>
    </form>

    <style>
        /* Sima, nem Livewire-alapú form -- lásd FirmwareUploadController
           doc-kommentjét arról, hogy MIÉRT nincs itt Filament FileUpload. */
        .fw-form { display: grid; gap: 16px; max-width: 720px; }
        .fw-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .fw-label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #e5e7eb; }
        .fw-required { color: #fb7185; margin-left: 2px; }
        .fw-input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 9px 12px;
            color: #e5e7eb;
            font-size: 14px;
        }
        .fw-help { color: #94a3b8; font-size: 12px; margin-top: 4px; }
        .fw-checkbox-row { display: flex; align-items: center; gap: 8px; }
        .fw-checkbox-row label { color: #e5e7eb; font-size: 14px; }
        .fw-actions { display: flex; gap: 10px; }
        .fw-btn {
            display: inline-flex;
            align-items: center;
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .fw-btn-secondary { background: #334155; }
        .fw-errors {
            background: rgba(248, 113, 113, .15);
            color: #fb7185;
            border-radius: 10px;
            padding: 10px 14px;
        }
        .fw-errors ul { margin: 0; padding-left: 18px; }
    </style>
</x-filament-panels::page>
