<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Sok magyar beléptető/időfigyelő rendszer "xls" exportja valójában egy HTML-táblázat
 * .xls kiterjesztéssel, gyakran Windows-1250/ISO-8859-2 kódolásban, charset= deklaráció
 * nélkül (vagy azzal, de a PhpSpreadsheet biztonsági szűrője akkor sem konvertálja).
 *
 * A PhpSpreadsheet HTML olvasója (Reader\Html::replaceNonAsciiIfNeeded()) egy /u módosítós
 * (UTF-8-at feltételező) reguláris kifejezéssel dolgozza fel a fájlt, HA az nem deklarál
 * charset-et — érvénytelen UTF-8 bájtsorozat (pl. ékezetes magyar karakter Windows-1250-ben)
 * esetén ez a preg_replace_callback NÉMÁN null-t ad vissza, ami "Failed to load file ...
 * as a DOM Document" hibaként jelentkezik, félrevezetve azt sugallva, hogy a fájl sérült.
 *
 * Ez az osztály a fájlt betöltés ELŐTT normalizálja: ha HTML-nek tűnik és nem érvényes
 * UTF-8, a deklarált (vagy feltételezett) kódolásból UTF-8-ra konvertálja egy ideiglenes
 * másolatban, mielőtt a PhpSpreadsheet-nek átadnánk. Valódi bináris XLS (OLE2) / XLSX (ZIP)
 * fájlokhoz nem nyúl.
 */
class SpreadsheetEncoding
{
    /** Betölti a fájlt PhpSpreadsheet-tel, szükség esetén UTF-8-ra normalizálva előtte. */
    public static function loadNormalized(string $filePath): Spreadsheet
    {
        $normalizedPath = self::normalize($filePath);

        try {
            return IOFactory::load($normalizedPath);
        } finally {
            if ($normalizedPath !== $filePath) {
                @unlink($normalizedPath);
            }
        }
    }

    /** A fájl elérési útja, szükség esetén egy UTF-8-ra konvertált ideiglenes másolatra mutatva. */
    public static function normalize(string $filePath): string
    {
        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return $filePath;
        }

        $head = ltrim(substr($raw, 0, 512));
        $looksLikeHtml = stripos($head, '<html') !== false
            || stripos($head, '<!doctype html') !== false
            || stripos($head, '<table') !== false;

        if (! $looksLikeHtml || mb_check_encoding($raw, 'UTF-8')) {
            return $filePath;
        }

        // A kódolást a fájl saját charset= deklarációjából olvassuk ki (megbízhatóbb, mint
        // a heurisztikus detektálás); ha nincs ilyen, a leggyakoribb magyar export-kódolást
        // (Windows-1250) feltételezzük.
        $charset = 'Windows-1250';
        if (preg_match('/charset\s*=\s*["\']?([\w-]+)/i', $raw, $m)) {
            $charset = $m[1];
        }

        $converted = @iconv($charset, 'UTF-8//IGNORE', $raw);
        if ($converted === false || $converted === '') {
            return $filePath;
        }

        // A charset= deklarációt is UTF-8-ra cseréljük, hogy a DOM-parser és a
        // PhpSpreadsheet biztonsági szűrője is helyesen ismerje fel az immár valódi UTF-8 tartalmat.
        $converted = preg_replace('/charset\s*=\s*["\']?[\w-]+/i', 'charset=UTF-8', $converted) ?? $converted;

        $tmpPath = tempnam(sys_get_temp_dir(), 'xls_utf8_') . '.xls';
        file_put_contents($tmpPath, $converted);

        return $tmpPath;
    }
}
