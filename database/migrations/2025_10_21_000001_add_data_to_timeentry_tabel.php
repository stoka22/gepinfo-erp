<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ha a mezők még nem léteznek, hozzuk létre őket nullable-ként.
        Schema::table('time_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('time_entries', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable()->after('note');
            }
            if (!Schema::hasColumn('time_entries', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('requested_by');
            }
        });

        // Ha léteznek, állítsuk nullable-re (ehhez kell doctrine/dbal).
        Schema::table('time_entries', function (Blueprint $table) {
            if (Schema::hasColumn('time_entries', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable()->change();
            }
            if (Schema::hasColumn('time_entries', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->change();
            }
        });

        // Opcionális indexek a gyorsításhoz (ha még nincsenek)
        try {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->index(['employee_id', 'type', 'start_date'], 'te_emp_type_start_idx');
                $table->index(['type', 'start_date', 'end_date'], 'te_type_start_end_idx');
            });
        } catch (\Throwable $e) {
            // ha már létezik az index, csendben továbbmegyünk
        }
    }

    public function down(): void
    {
        // Visszaállítás előtt töltsük fel a NULL értékeket egy létező user ID-val.
        // Ha nincs user, 1-et használunk (csak hogy a NOT NULL változtatás lefusson).
        $fallbackUserId = DB::table('users')->min('id') ?? 1;

        DB::table('time_entries')->whereNull('requested_by')->update(['requested_by' => $fallbackUserId]);
        DB::table('time_entries')->whereNull('approved_by')->update(['approved_by' => $fallbackUserId]);

        Schema::table('time_entries', function (Blueprint $table) {
            if (Schema::hasColumn('time_entries', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable(false)->change();
            }
            if (Schema::hasColumn('time_entries', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable(false)->change();
            }

            // Indexek visszaszedése (ha szeretnéd teljes rollbacket)
            try {
                $table->dropIndex('te_emp_type_start_idx');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('te_type_start_end_idx');
            } catch (\Throwable $e) {}
        });
    }
};
