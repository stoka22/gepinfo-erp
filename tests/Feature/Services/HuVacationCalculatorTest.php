<?php

use App\Models\Employee;
use App\Services\Vacation\HuVacationCalculator;

it('gives full base days and the correct age-extra for a full-year employee', function () {
    $calc = new HuVacationCalculator();
    $employee = new Employee(['hired_at' => '2015-03-01', 'birth_date' => '1990-06-15']);

    $result = $calc->calculate($employee, 2026);

    // 1990-06-15 -> 36 éves lesz 2026.12.31-én => Mt. lépcső szerint +5 nap
    expect($result['base'])->toBe(20.0)
        ->and($result['age_extra'])->toBe(5.0);
});

it('prorates base and age-extra days for a mid-year hire', function () {
    $calc = new HuVacationCalculator();
    $employee = new Employee(['hired_at' => '2026-07-01', 'birth_date' => '1990-06-15']);

    $result = $calc->calculate($employee, 2026);

    expect($result['base'])->toBe(10.1)
        ->and($result['age_extra'])->toBe(2.5);
});

it('gives zero age-extra when there is no birth date', function () {
    $calc = new HuVacationCalculator();
    $employee = new Employee(['hired_at' => '2020-01-01', 'birth_date' => null]);

    $result = $calc->calculate($employee, 2026);

    expect($result['base'])->toBe(20.0)
        ->and($result['age_extra'])->toBe(0.0);
});

it('gives zero age-extra for employees under 25', function () {
    $calc = new HuVacationCalculator();
    $employee = new Employee(['hired_at' => '2020-01-01', 'birth_date' => '2005-01-01']);

    $result = $calc->calculate($employee, 2026);

    expect($result['age_extra'])->toBe(0.0);
});
