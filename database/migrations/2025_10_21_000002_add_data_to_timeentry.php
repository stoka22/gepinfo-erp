<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $t) {
            if (!Schema::hasColumn('time_entries', 'entry_method')) {
                // 'card' = kártyás; 'office' = irodai rögzítés
                $t->string('entry_method', 20)->nullable()->after('note');
            }
            if (!Schema::hasColumn('time_entries', 'is_modified')) {
                $t->boolean('is_modified')->default(false)->after('entry_method');
            }
            if (!Schema::hasColumn('time_entries', 'modified_by')) {
                $t->unsignedBigInteger('modified_by')->nullable()->after('is_modified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $t) {
            if (Schema::hasColumn('time_entries', 'modified_by')) $t->dropColumn('modified_by');
            if (Schema::hasColumn('time_entries', 'is_modified')) $t->dropColumn('is_modified');
            if (Schema::hasColumn('time_entries', 'entry_method')) $t->dropColumn('entry_method');
        });
    }
};
