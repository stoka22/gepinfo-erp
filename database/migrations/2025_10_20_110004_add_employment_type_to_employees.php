<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->string('employment_type', 32)->default('full_time')->after('company_id');
            // (ha nincs még) $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            
            $t->dropColumn('employment_type');
        });
    }
};
