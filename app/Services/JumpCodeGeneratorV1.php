<?php
namespace App\Services;

class JumpCodeGeneratorV1
{
    public function generate(string|int $key): string
    {
        // normalize input: keep digits only, pad/trim to last 5 digits
        $s = preg_replace('/\D/', '', (string)$key);
        $s = str_pad($s, 5, '0', STR_PAD_LEFT);
        if (strlen($s) > 5) $s = substr($s, -5);

        $digits = array_map('intval', str_split($s)); // d[0]..d[4]
        [$d1, $d2, $d3, $d4, $d5] = $digits;

        // helper used for P (and earlier attempt)
        $f_p = function(int $p, int $t): int {
            if ($p < 2) {
                if ($t === 0) {
                    return $t + 1;
                }
                return $t + $p;
            } else {
                $sum = $p + $t;
                if ($sum > 9) {
                    $m = $sum % 10;
                    return $m === 0 ? 1 : $m;
                } else {
                    return $sum;
                }
            }
        };

        // P output (leftmost)
        $out1 = $f_p($d1, $d5);

        // Q output - **YOUR CORRECTED FORMULA**
        // =IF(T3="0"; IF(S3="0"; 1; S3); MOD(T3+S3,10))
        if ($d5 === 0) {
            $out2 = ($d4 === 0) ? 1 : $d4;
        } else {
            $out2 = ($d5 + $d4) % 10;
        }

        // R output (uses original digits d1,d2)
        if ($d1 < 2) {
            if ($d2 < 2) {
                $out3 = 1;
            } else {
                $out3 = $d2 + $d1;
            }
        } else {
            $out3 = ($d1 + $d2) % 10;
        }

        // S output - uses out3 (R result) in the <2 branch
        if ($d2 < 2) {
            $tmp = $out3 + 1;
            $out4 = ($tmp === 10) ? 1 : $tmp;
        } else {
            $m = ($d2 + $out3) % 10;
            $out4 = ($m === 0) ? 1 : $m;
        }

        // T output - according to the inferred rule (d2 + d3)
        $mT = ($d2 + $d3) % 10;
        $out5 = ($mT === 0) ? 1 : $mT;

        // assemble and return
        $outs = [
            $out1 % 10,
            $out2 % 10,
            $out3 % 10,
            $out4 % 10,
            $out5 % 10,
        ];

        return implode('', $outs);
    }

    public function generateVariant(int $variant, string|int $key): string
{
    return match($variant) {
        1 => $this->generate($key),
        2 => $this->generateVariant2($key), // vagy delegálj V2 service-nek
        3 => $this->generateVariant3($key),
        default => throw new \InvalidArgumentException("Unknown variant: $variant"),
    };
}

// ha nincs V2 logika itt, delegáld:
protected function generateVariant2(string|int $key): string
{
    if (class_exists(\App\Services\JumpCodeGeneratorV2::class)) {
        return app()->make(\App\Services\JumpCodeGeneratorV2::class)->generate($key);
    }
    // egyéb fallback vagy hiba
    throw new \RuntimeException('V2 generator not available');
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
