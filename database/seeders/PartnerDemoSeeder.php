<?php
// database/seeders/PartnerDemoSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Company, User, Partner};

class PartnerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $c1 = Company::firstOrCreate(['name'=>'Acme Kft.'], ['group'=>1]);
        $c2 = Company::firstOrCreate(['name'=>'Beta Zrt.'], ['group'=>2]);

        $admin = User::firstOrCreate(
            ['email'=>'admin@example.com'],
            ['name'=>'Admin', 'password'=>bcrypt('password'), 'role'=>'admin', 'company_id'=>$c1->id, 'group'=>1]
        );

        $user = User::firstOrCreate(
            ['email'=>'user@example.com'],
            ['name'=>'Felhasználó', 'password'=>bcrypt('password'), 'role'=>'user', 'company_id'=>$c1->id, 'group'=>1]
        );

        $p1 = Partner::create(['name'=>'MetalTrade Kft.','tax_id'=>'HU12345678','owner_company_id'=>$c1->id,'is_supplier'=>1,'is_customer'=>0]);
        $p2 = Partner::create(['name'=>'CityBuild Zrt.','tax_id'=>'HU87654321','owner_company_id'=>$c2->id,'is_supplier'=>0,'is_customer'=>1]);

        $p1->companies()->sync([$c1->id]);         // saját partner
        $p2->companies()->sync([$c1->id, $c2->id]); // megosztott
    }
}
