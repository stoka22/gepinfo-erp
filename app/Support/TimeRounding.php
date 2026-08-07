<?php

namespace App\Support;

class TimeRounding
{
    /** Munkakezdés fél órára felfelé kerekítve, pl. 06:04 -> 06:30, 05:56 -> 06:00. */
    public static function roundStartUpToHalfHour(string $hm): string
    {
        [$h, $m] = array_map('intval', explode(':', $hm));

        if ($m === 0) {
            return sprintf('%02d:00', $h);
        }
        if ($m <= 30) {
            return sprintf('%02d:30', $h);
        }
        return sprintf('%02d:00', ($h + 1) % 24);
    }

    /**
     * Munkakezdés EGÉSZ órára felfelé kerekítve — a nap ELSŐ bejelentkezésére vonatkozik,
     * pl. 05:37 -> 06:00, 05:56 -> 06:00, 06:00 -> 06:00. A nap további (ebéd utáni stb.)
     * be-/kilépései és minden kilépés percre pontos marad, ez a kerekítés csak a napi
     * első kezdésre alkalmazandó (ld. OvertimeBalanceService::effectiveStartLabel()).
     */
    public static function roundStartUpToWholeHour(string $hm): string
    {
        [$h, $m] = array_map('intval', explode(':', $hm));

        if ($m === 0) {
            return sprintf('%02d:00', $h);
        }
        return sprintf('%02d:00', ($h + 1) % 24);
    }
}
