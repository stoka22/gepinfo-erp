<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeesSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Agócs Roland',
            'none',
            'Boros Töhötöm',
            'Borosné Kovács Krisztina',
            'Csiszár Józsefné',
            'Fekete Cecília',
            'Gencsi Janka',
            'Gyenei Gyöngyi',
            'JOBBÁGY JÓZSEF',
            'KARDOS KINGA',
            'KESZTE GYULA',
            'Gulyás Szilveszter',
            'Tóth Mónika',
            'Kristóf András',
            'Krnk Tiborné',
            'Nagy Noémi Pálma',
            'Pálkovács Béláné',
            'PUNGOR ISTVÁN',
            'Szabó László',
            'Szabóné Szeifert Anna Mária',

            'Apáti Ferenc',
            'Bessenyeiné G. Gabriella',
            'Bódi Szabolcs',
            'Doszpod Tamás',
            'Eigenbrót Adrienn',
            'Farkas Kinga',
            'Farkas Tibor',
            'Fodor Milán',
            'Gulyás Józsefné',
            'JANKA ROLAND',
            'Kis László',
            'Kocsándi Tibor József',
            'Kőváriné Karsai Bernadett',
            'Milisitsné Csonka Ildikó',
            'Molnárné Kiss Mária',
            'Mózes Richárd',
            'Németh K. Lászlóné',
            'Orbán Tünde',
            'Orsós Katalin',
            'Peterdiné R. Veronika',
            'Rufli Csilla',
            'Sára Lajos Péter',
            'Simonné Molnár Ildikó',
        ];

        $now = now();

        $rows = array_map(fn ($n) => [
            'name' => $n,
            'email' => null,
            'phone' => null,
            'position' => null,
            'position_id' => null,
            'user_id' => 7,
            'is_disabled' => 0,          // ha nem nullázható
            'children_under_16' => 0,    // ha nem nullázható
            'birth_date' => null,
            'hired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names);

        DB::table('employees')->insert($rows);
    }
}
