<?php
namespace App\Services;

class JumpCodeGeneratorV3
{
    public function generate(string|int $key): string
    {
        $s = preg_replace('/\D/', '', (string)$key);
        $s = str_pad($s, 6, '0', STR_PAD_LEFT);
        $s = substr($s, -6);

        $chars = str_split($s);
        $d = array_map(fn($c) => intval($c), $chars);

        $coeffs = [
            [3, 3, 2, 0, 0, 0],
            [1, 6, 2, 0, 0, 0],
            [1, 2, 6, 0, 0, 0],
            [3, 8, 6, 0, 0, 0],
            [4, 0, 5, 0, 0, 0],
            [3, 3, 3, 0, 0, 0],
            [1, 3, 1, 0, 0, 0],
            [0, 0, 0, 0, 0, 0],
            [3, 0, 6, 0, 0, 0],
        ];

        $consts = [1,0,1,1,1,1,1,0,1];

        $out = '';
        foreach ($coeffs as $pos => $coefRow) {
            $sum = 0;
            for ($i = 0; $i < 6; $i++) {
                $sum += $coefRow[$i] * $d[$i];
            }
            $sum += $consts[$pos];
            $v = $sum % 10;
            $out .= (string)$v;
        }

        return $out;
    }
}
