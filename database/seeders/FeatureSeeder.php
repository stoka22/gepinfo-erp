<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key'=>'partners',        'name'=>'Partnerek',       'group'=>'Partnerek & ügyfelek'],
            ['key'=>'warehouse',       'name'=>'Raktárak',        'group'=>'Raktár'],
            ['key'=>'items',           'name'=>'Tételek',         'group'=>'Raktár'],
            ['key'=>'goods-receipts',  'name'=>'Bevételezések',   'group'=>'Raktár'],
            // továbbiak...
        ];

        foreach ($rows as $r) {
            Feature::firstOrCreate(['key'=>$r['key']], $r);
        }
    }
}
