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
 * Emellett az Excel "mentés weblapként" export gyakran egy régi CSS-elrejtő trükköt
 * használ a <style> blokkban (<!--table {...}-->), és Microsoft-féle "downlevel-revealed"
 * feltételes jelölőket (<![if !supportTabStrip]> ... <![endif]>, "<!--" nélkül) — mindkettő
 * megtöri a libxml HTML-parserét. Nagy (több tízezer soros) exportoknál ez néhány sor után
 * teljesen leállítja a feldolgozást, adatvesztést okozva anélkül, hogy bármilyen hiba
 * jelentkezne (a loadHTML() sikeresnek jelzi magát, csak a dokumentum eleje épül fel).
 *
 * Ez az osztály a fájlt betöltés ELŐTT normalizálja: ha HTML-nek tűnik, szükség esetén a
 * deklarált (vagy feltételezett) kódolásból UTF-8-ra konvertálja, és mindig eltávolítja az
 * adatimporthoz irreleváns <style>/<script> blokkokat és a feltételes jelölőket, egy
 * ideiglenes másolatban, mielőtt a PhpSpreadsheet-nek átadnánk. Valódi bináris XLS (OLE2) /
 * XLSX (ZIP) fájlokhoz nem nyúl.
 *
 * Emellett a betöltés maga is védett: a DOMDocument::loadHTML() akár egy ártalmatlan
 * figyelmeztetésnél (pl. duplikált id egy Excel-export <link>/<a name> elemén) is PHP
 * warningot dob, amit Laravel kivétellé alakít — a PhpSpreadsheet Html olvasója viszont
 * BÁRMILYEN kivételt végzetes "Failed to load ... as a DOM Document" hibaként kezel, még ha
 * a DOM valójában hibátlanul felépült. A libxml belső hibakezelésének bekapcsolása ezt a
 * hamis-negatívot szünteti meg, a hívás után visszaállítva az eredeti állapotot.
 */
class SpreadsheetEncoding
{
    /**
     * Néhány valódi bináris (OLE2) .xls beléptető-exportnál a PhpSpreadsheet Xls olvasója
     * a magyar kettős ékezetes magánhangzókat (ő, ű) rossz kódlapon dekódolja: a helyes
     * UTF-8 bájtsorozatot (pl. "ő" = 0xC5 0x91) Windows-1252-ként értelmezi újra, "Å‘"-höz
     * hasonló, felismerhetetlen névtörzset eredményezve (pl. "Törő Tibor" -> "TörÅ‘ Tibor").
     * Ez importáláskor dolgozó-névegyeztetési hibákat okoz. A hibás bájtmintázat pontosan
     * ismert és egyértelműen visszafordítható, ezért csak a hibás részsorozatot cseréljük,
     * a string többi, helyesen dekódolt részéhez nem nyúlunk. (Az "Ő" nagybetűs alak nem
     * javítható vissza: a hozzá tartozó CP1252-bájt Windows-1252-ben nincs hozzárendelve
     * egyetlen karakterhez sem, az információ már a beolvasásnál elveszett.)
     */
    public static function fixMojibake(?string $value): ?string
    {
        if ($value === null || $value === '' || ! str_contains($value, 'Å')) {
            return $value;
        }

        return strtr($value, [
            "Å‘" => 'ő',
            "Å±" => 'ű',
            "Å°" => 'Ű',
        ]);
    }

    /** Betölti a fájlt PhpSpreadsheet-tel, szükség esetén normalizálva előtte. */
    public static function loadNormalized(string $filePath): Spreadsheet
    {
        $normalizedPath = self::normalize($filePath);
        $previousLibxmlSetting = libxml_use_internal_errors(true);

        try {
            return IOFactory::load($normalizedPath);
        } finally {
            libxml_use_internal_errors($previousLibxmlSetting);

            if ($normalizedPath !== $filePath) {
                @unlink($normalizedPath);
            }
        }
    }

    /** A fájl elérési útja, szükség esetén egy normalizált (UTF-8, style/script nélküli) ideiglenes másolatra mutatva. */
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

        if (! $looksLikeHtml) {
            return $filePath;
        }

        $content = $raw;
        $changed = false;

        if (! mb_check_encoding($raw, 'UTF-8')) {
            // A kódolást a fájl saját charset= deklarációjából olvassuk ki (megbízhatóbb,
            // mint a heurisztikus detektálás); ha nincs ilyen, a leggyakoribb magyar
            // export-kódolást (Windows-1250) feltételezzük.
            $charset = 'Windows-1250';
            if (preg_match('/charset\s*=\s*["\']?([\w-]+)/i', $raw, $m)) {
                $charset = $m[1];
            }

            $converted = @iconv($charset, 'UTF-8//IGNORE', $raw);
            if ($converted !== false && $converted !== '') {
                // A charset= deklarációt is UTF-8-ra cseréljük, hogy a DOM-parser és a
                // PhpSpreadsheet biztonsági szűrője is helyesen ismerje fel a tartalmat.
                $content = preg_replace('/charset\s*=\s*["\']?[\w-]+/i', 'charset=UTF-8', $converted) ?? $converted;
                $changed = true;
            }
        }

        $cleaned = preg_replace('#<style\b.*?</style>#is', '', $content) ?? $content;
        $cleaned = preg_replace('#<script\b.*?</script>#is', '', $cleaned) ?? $cleaned;
        // Microsoft "downlevel-revealed" feltételes jelölők: <![if ...]> és <![endif]>
        // (a szabványos <!--[if ...]> ... <![endif]--> változattól eltérően "<!--" nélkül) —
        // csak a jelölőket távolítjuk el, a köztük lévő tartalmat érintetlenül hagyva.
        $cleaned = preg_replace('/<!\[\s*if\b[^\]]*\]>/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<!\[\s*endif\s*\]>/i', '', $cleaned) ?? $cleaned;

        if ($cleaned !== $content) {
            $content = $cleaned;
            $changed = true;
        }

        if (! $changed) {
            return $filePath;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'xls_norm_') . '.xls';
        file_put_contents($tmpPath, $content);

        return $tmpPath;
    }
}
