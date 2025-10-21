<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->after('id');
        });

        // backfill: account_user_id -> users.company_id, ha nincs, akkor created_by_user_id
        DB::statement("
            UPDATE employees e
            LEFT JOIN users ua ON ua.id = e.account_user_id
            LEFT JOIN users uc ON uc.id = e.created_by_user_id
            SET e.company_id = COALESCE(ua.company_id, uc.company_id)
            WHERE e.company_id IS NULL
        ");

        Schema::table('employees', function (Blueprint $t) {
            $t->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $t->index('company_id');
            // ha kötelezőnek szeretnéd:
            // $t->foreignId('company_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropColumn('company_id');
        });
    }
};
