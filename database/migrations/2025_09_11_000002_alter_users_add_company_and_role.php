<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // company_id (FK a companies.id-re) – csak ha még nincs
            if (! Schema::hasColumn('users', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->constrained('companies')
                    ->nullOnDelete();
            }

            // role – csak ha még nincs
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->index();
            }

            // group – csak ha még nincs
            if (! Schema::hasColumn('users', 'group')) {
                $table->unsignedTinyInteger('group')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Biztonságos visszafelé lépések (ha léteznek)
            if (Schema::hasColumn('users', 'company_id')) {
                // előbb a FK törlése, ha van
                try { $table->dropForeign(['company_id']); } catch (\Throwable $e) {}
                $table->dropColumn('company_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropIndex(['role']); // lehet, hogy nem létezik — nem gond, try/catch nélkül is elmegy sok DB-n
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'group')) {
                $table->dropIndex(['group']);
                $table->dropColumn('group');
            }
        });
    }
};
