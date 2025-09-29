<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('pending_devices', function (Blueprint $t) {
            $t->id();
            $t->string('mac_address')->unique();
            $t->string('proposed_name')->nullable();
            $t->string('fw_version')->nullable();
            $t->string('ip')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_devices');
    }
};
