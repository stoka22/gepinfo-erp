<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSheetBatchDownloadController extends Controller
{
    /**
     * A GenerateAttendanceSheetBatchJob háttérben előállított PDF-jét szolgálja ki — a
     * $token azonosítja a lemezen a mappát (tárolt fájlnevet path traversal ellen
     * basename()-elve vesszük), a $filename csak a böngészőnek megjelenítendő névre kell.
     */
    public function download(string $token, string $filename): StreamedResponse
    {
        $safeToken = preg_replace('/[^a-f0-9\-]/i', '', $token);
        $safeFilename = basename($filename);
        $path = "attendance-sheets/{$safeToken}/{$safeFilename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $safeFilename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
