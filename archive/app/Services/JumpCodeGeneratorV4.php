<?php
namespace App\Services;
class JumpCodeGeneratorV2
{
    public function generate(string|int $key): string
    {
        $s = preg_replace('/\D/', '', (string)$key);
        $s = str_pad($s, 6, '0', STR_PAD_LEFT);
        $s = substr($s, -6);
        $chars = str_split($s);
        $d = array_map(fn($c)=>intval($c), $chars);

        $params = [
            [1, 1, 0b010011, 2], // pos0
            [7, 0, 0b101000, 1], // pos1
            [1, 2, 0b100011, 0], // pos2
            [1, 0, 0b101000, 4], // pos3
            [9, 0, 0b000001, 1], // pos4
            [3, 0, 0b001001, 3], // pos5
            [9, 0, 0b110000, 6], // pos6
            [0, 0, 0b000000, 0], // pos7
            [3, 0, 0b110011, 0], // pos8
        ];

        $out = '';
        foreach ($params as [$a,$b,$mask,$const]) {
            $sum = 0;
            for ($i=0;$i<6;$i++) {
                if ((($mask >> $i) & 1) === 1) {
                    $val = ($a * $d[$i] + $b) % 10;
                    $val = ($val + 10) % 10;
                    $sum += $val;
                }
            }
            $sum += $const;
            $out .= (string)(($sum % 10 + 10) % 10);
        }
        return $out;
    }
}