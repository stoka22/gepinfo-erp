<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_company_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('role')->nullable();        // opcionális: betöltött szerep a cégnél
            $t->boolean('active')->default(true);  // gyors szűrés
            $t->timestamps();
            $t->unique(['employee_id','company_id']); // egy sor / cég
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            
            $t->dropColumn('employment_type');
        });
    }
};
