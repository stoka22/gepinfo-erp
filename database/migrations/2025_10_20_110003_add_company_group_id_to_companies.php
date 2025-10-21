<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->foreignId('company_group_id')->nullable()->after('id')
            ->constrained('company_groups')->nullOnDelete();
            $t->index('company_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropForeign(['company_group_id']);
            $t->dropColumn('company_group_id');
        });
    }
};
