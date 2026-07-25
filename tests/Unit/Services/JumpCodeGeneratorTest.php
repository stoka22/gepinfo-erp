<?php

use App\Services\JumpCodeGeneratorV1;
use App\Services\JumpCodeGeneratorV2;
use App\Services\JumpCodeGeneratorV3;

// Ezek a generátorok visszafejtett, Excel-képletből származó checksum-algoritmusok
// (ld. a kódban lévő kommenteket). A tesztek a JELENLEGI kimenetet rögzítik
// referenciaértékként, hogy egy véletlen módosítás (pl. refaktor) azonnal
// kiderüljön, nem azt igazolják, hogy az algoritmus "helyes".

it('V1 generates the pinned reference codes', function () {
    $gen = new JumpCodeGeneratorV1();

    expect($gen->generate('12345'))->toBe('69357')
        ->and($gen->generate('00000'))->toBe('11111')
        ->and($gen->generate('99999'))->toBe('88888')
        ->and($gen->generate('1'))->toBe('11111')
        ->and($gen->generate('123456'))->toBe('81579')
        ->and($gen->generate('654321'))->toBe('63975');
});

it('V2 generates the pinned reference codes', function () {
    $gen = new JumpCodeGeneratorV2();

    expect($gen->generate('12345'))->toBe('75468')
        ->and($gen->generate('00000'))->toBe('01111')
        ->and($gen->generate('99999'))->toBe('88888')
        ->and($gen->generate('1'))->toBe('11111')
        ->and($gen->generate('123456'))->toBe('97681')
        ->and($gen->generate('654321'))->toBe('57864');
});

it('V3 generates the pinned reference codes', function () {
    $gen = new JumpCodeGeneratorV3();

    expect($gen->generate('12345'))->toBe('805110603')
        ->and($gen->generate('00000'))->toBe('101111101')
        ->and($gen->generate('99999'))->toBe('623765705')
        ->and($gen->generate('1'))->toBe('101111101')
        ->and($gen->generate('123456'))->toBe('694809102')
        ->and($gen->generate('654321'))->toBe('241356603');
});

it('V2 generateVariant(2, ...) delegates to its own generate() without recursing infinitely', function () {
    // Regresszió: generateVariant2() korábban önmagát (JumpCodeGeneratorV2-t)
    // példányosította és hívta meg, ami végtelen rekurziót okozott volna.
    $gen = new JumpCodeGeneratorV2();

    expect($gen->generateVariant(2, '12345'))->toBe($gen->generate('12345'));
});

it('V1 generateVariant(2/3, ...) delegates to V2/V3', function () {
    $v1 = new JumpCodeGeneratorV1();
    $v2 = new JumpCodeGeneratorV2();
    $v3 = new JumpCodeGeneratorV3();

    expect($v1->generateVariant(2, '12345'))->toBe($v2->generate('12345'))
        ->and($v1->generateVariant(3, '12345'))->toBe($v3->generate('12345'));
});
