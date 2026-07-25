<?php

namespace App\Services;

class JumpCodeGeneratorV2
{
    public function generate(string|int $key): string
    {
        // normalize input: keep digits only, pad/trim to last 5 digits
        $s = preg_replace('/\D/', '', (string)$key);
        $s = str_pad($s, 5, '0', STR_PAD_LEFT);
        if (strlen($s) > 5) $s = substr($s, -5);

        $digits = array_map('intval', str_split($s)); // d[0]..d[4]
        [$d1, $d2, $d3, $d4, $d5] = array_map('intval', $digits);

        // helper used for P (and earlier attempt)


        // P output (leftmost)
        $out1 = ($d2 + $d5) % 10;

        // Q output - **YOUR CORRECTED FORMULA**
        // =IF(T3="0"; IF(S3="0"; 1; S3); MOD(T3+S3,10))
        //MARADÉK(F2+I2;10)
        $out2 = ($d1 == 0) ? 1 : ($d1 + $d4) % 10;

        // R output (uses original digits d1,d2)
        $out3 = ($d1 == 0) ? 1 : ($d1 + $d3) % 10;

        // S output - uses out3 (R result) in the <2 branch
        // =HA(Q3<"2";HA((R3+1)=10;1;R3+1);HA(MARADÉK(Q3+R3;10)=0;1;MARADÉK(Q3+R3;10)))
        $out4 = ((($d2 + $d4) % 10) == 0) ? 1 : (($d2 + $d4) % 10);

        // T output - according to the inferred rule (d2 + d3)

        $out5 = ($d5 == 0) ? 1 : (($d3 + $d5 == 10) ? 1 : (($d3 + $d5) % 10));

        // assemble and return
        $outs = [$out1, $out2, $out3, $out4, $out5];

        return implode('', $outs);
    }

    public function generateVariant(int $variant, string|int $key): string
    {
        return match ($variant) {
            1 => $this->generate($key),
            2 => $this->generateVariant2($key), // vagy delegálj V2 service-nek
            3 => $this->generateVariant3($key),
            4 => $this->generateVariant3($key),
            default => throw new \InvalidArgumentException("Unknown variant: $variant"),
        };
    }

    protected function generateVariant2(string|int $key): string
    {
        return $this->generate($key);
    }

    protected function generateVariant3(string|int $key): string
    {
        if (class_exists(\App\Services\JumpCodeGeneratorV3::class)) {
            return app()->make(\App\Services\JumpCodeGeneratorV3::class)->generate($key);
        }
        // egyéb fallback vagy hiba
        throw new \RuntimeException('V2 generator not available');
    }
}
