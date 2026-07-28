<?php

use App\Support\SpreadsheetEncoding;

function fakeXlsFile(string $htmlUtf8, ?string $encodeAs = null): string
{
    $path = tempnam(sys_get_temp_dir(), 'spreadsheet_test_') . '.xls';
    $content = $encodeAs ? iconv('UTF-8', $encodeAs, $htmlUtf8) : $htmlUtf8;
    file_put_contents($path, $content);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/spreadsheet_test_*.xls') as $leftover) {
        @unlink($leftover);
    }
});

it('loads a Windows-1250-encoded HTML "fake xls" without a charset declaration (the reported production crash)', function () {
    // Ez pontosan az a helyzet, ami éles környezetben "Failed to load file ... as a DOM
    // Document" hibát okozott: HTML-táblázat .xls kiterjesztéssel, magyar ékezetes
    // karakterekkel, Windows-1250 kódolásban, charset= deklaráció nélkül.
    $html = "<html><head><meta name=\"Excel Workbook Frameset\"></head>"
        . "<body><table><tr><td>Nagy Noémi Pálma</td><td>Összeszerelő</td></tr></table></body></html>";
    $path = fakeXlsFile($html, 'Windows-1250');

    $sheet = SpreadsheetEncoding::loadNormalized($path)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Nagy Noémi Pálma');
    expect($sheet->getCell('B1')->getValue())->toBe('Összeszerelő');

    unlink($path);
});

it('loads a Windows-1250-encoded HTML "fake xls" that does declare a charset', function () {
    $html = "<html><head><meta http-equiv=Content-Type content=\"text/html; charset=windows-1250\"></head>"
        . "<body><table><tr><td>Kovács János</td></tr></table></body></html>";
    $path = fakeXlsFile($html, 'Windows-1250');

    $sheet = SpreadsheetEncoding::loadNormalized($path)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Kovács János');

    unlink($path);
});

it('leaves an already-valid-UTF-8 HTML "fake xls" untouched', function () {
    $html = "<html><body><table><tr><td>Nagy Noémi Pálma</td></tr></table></body></html>";
    $path = fakeXlsFile($html); // already UTF-8, no conversion

    $sheet = SpreadsheetEncoding::loadNormalized($path)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Nagy Noémi Pálma');

    unlink($path);
});
