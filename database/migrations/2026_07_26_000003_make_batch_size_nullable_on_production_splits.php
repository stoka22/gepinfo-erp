<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A TaskController mindig explicit NULL-t ír, ha a kliens nem küld batchSize-t
        // (a kód logikája is "nincs batch" jelentéssel kezeli); az oszlop viszont
        // NOT NULL volt, ami minden ilyen kérésnél SQL hibát okozott.
        Schema::table('production_splits', function (Blueprint $table) {
            $table->unsignedInteger('batch_size')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_splits', function (Blueprint $table) {
            $table->unsignedInteger('batch_size')->default(100)->change();
        });
    }
};
