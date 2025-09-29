<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partner_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('est_duration_sec')->nullable()->after('due_date'); // össz-idő másodpercben
            $table->timestamp('est_finish_at')->nullable()->after('est_duration_sec');     // várható befejezés (naiv)
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_order_items', function (Blueprint $table) {
            //
        });
    }
};
