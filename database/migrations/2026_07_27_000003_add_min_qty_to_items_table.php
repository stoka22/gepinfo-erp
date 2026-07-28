<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Rendelési szint: ha kitöltött és a készlet ez alá esik, a napi riasztás jelzi.
            // NULL = nincs figyelve (alapértelmezett, hogy ne legyen zajos, amíg be nem állítják).
            $table->decimal('min_qty', 12, 3)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('min_qty');
        });
    }
};
