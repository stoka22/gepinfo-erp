<?php
// app/Services/Vacation/HuVacationCalculator.php
namespace App\Services\Vacation;

use App\Models\Employee;
use Illuminate\Support\Carbon;

class HuVacationCalculator
{
    public function calculate(Employee $e, int $year): array
    {
        $jan1 = Carbon::create($year, 1, 1);
        $dec31 = Carbon::create($year, 12, 31);

        $hire = $e->hired_at ? Carbon::parse($e->hired_at) : $jan1;
        $periodStart = $hire->greaterThan($jan1) ? $hire : $jan1;

        $total = $jan1->diffInDays($dec31) + 1;
        $employed = max(0, $periodStart->diffInDays($dec31) + 1);
        $ratio = $total > 0 ? $employed / $total : 1.0;

        // Alapszabadság: 20 nap/év (Mt.), belépésnél arányosítva
        $base = 20.0 * $ratio;

        // Életkor szerinti pótszabi: az adott évben betöltött kor alapján, arányosítva
        $ageExtraFull = $this->ageExtraDays($e->birth_date ?? null, $year);
        $ageExtra = $ageExtraFull * $ratio;

        return [
            'base'      => round($base, 1),
            'age_extra' => round($ageExtra, 1),
        ];
    }

    protected function ageExtraDays(?string $birthDate, int $year): int
    {
        if (!$birthDate) return 0;
        $age = Carbon::parse($birthDate)->diffInYears(Carbon::create($year, 12, 31));
        // Mt. életkor pótszabi lépcsők:
        return match (true) {
            $age < 25 => 0,
            $age < 28 => 1,
            $age < 31 => 2,
            $age < 33 => 3,
            $age < 35 => 4,
            $age < 37 => 5,
            $age < 39 => 6,
            $age < 41 => 7,
            $age < 43 => 8,
            $age < 45 => 9,
            default   => 10,
        };
    }
}
