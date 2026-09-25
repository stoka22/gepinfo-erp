<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\FirmwareResource;
use App\Http\Controllers\Controller;
use App\Models\Firmware;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sima, NEM Livewire-alapú firmware-feltöltés. A Filament FileUpload
 * komponens a /livewire/upload-file végpontot használja egy kétlépéses
 * (ideiglenes feltöltés, majd form-mentéskor átmozgatás) folyamatban --
 * élesben ez a végpont KONZISZTENSEN 406-ot kapott egy, a szerver elé
 * rakott WAF-szabálytól bináris (.bin) tartalomra, MÉG MIELŐTT a kérés
 * PHP-ig eljutna. Megerősítve (2026-09-25): egy azonos méretű véletlen
 * bináris egy SIMA multipart POST-tal (nem Livewire-en keresztül)
 * hibátlanul átment ugyanarra a szerverre -- tehát nem a bináris tartalom
 * általában, kifejezetten a Livewire végpont van blokkolva. Az Energy
 * projekt (FirmwareReleaseController) pontosan ezért NEM Filamenttel
 * oldja meg a firmware-feltöltést, hanem egy sima <form>+kontroller
 * párossal -- ez a kontroller azt a bevált mintát követi.
 */
class FirmwareUploadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_id' => ['nullable', 'exists:devices,id'],
            'platform' => ['required', 'in:esp32,esp8266'],
            'hardware_code' => ['nullable', 'string', 'max:64'],
            'version' => ['required', 'string', 'max:32'],
            'build' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Az ESP32/ESP8266 firmware-ek jellemzően 1-2 MB-osak; 100 MB
            // bőséges felső korlát, ugyanaz, amit a korábbi FileUpload
            // komponens is használt (maxSize(100*1024) KB-ban).
            'firmware' => ['required', 'file', 'max:'.(100 * 1024)],
        ]);

        $file = $request->file('firmware');
        // Egyedi fájlnév a lemezen (nem az eredeti névvel, hogy sose
        // ütközzön) -- a letöltési endpoint (FirmwareResource) amúgy is
        // "{version}.bin" néven kínálja fel, a lemez-fájlnév nem
        // felhasználó-szembeni.
        $path = $file->storeAs(
            'firmware',
            Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'bin'),
            'local'
        );

        // A Firmware::booted()::saved hook automatikusan kitölti a
        // file_size/mime_type/sha256/md5/published_at mezőket a ténylegesen
        // lemezre írt fájlból -- itt nem kell duplikálni.
        Firmware::create([
            'device_id' => $validated['device_id'] ?? null,
            'platform' => $validated['platform'],
            'hardware_code' => $validated['hardware_code'] ?? null,
            'version' => $validated['version'],
            'build' => $validated['build'] ?? 1,
            'forced' => $request->boolean('forced'),
            'notes' => $validated['notes'] ?? null,
            'file_path' => $path,
        ]);

        // Notification::make()->send() session-flashel, ami a KÖVETKEZŐ
        // Filament-oldal betöltésekor (itt: index, ami egy MÁSIK Livewire
        // komponens, mint ez a sima kontroller-akció) is megjelenik --
        // ezért működik plain redirect()-tel is, nem csak Livewire
        // action-ökből hívva.
        Notification::make()
            ->title('Firmware feltöltve')
            ->success()
            ->send();

        return redirect(FirmwareResource::getUrl('index'));
    }
}
