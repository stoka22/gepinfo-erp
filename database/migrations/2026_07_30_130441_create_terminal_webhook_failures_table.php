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
        Schema::create('terminal_webhook_failures', function (Blueprint $table) {
            $table->id();
            $table->string('error_code'); // unauthorized|unknown_card|no_open_entry|validation|no_system_user
            $table->unsignedSmallInteger('http_status');
            $table->string('card_uid')->nullable();
            $table->string('direction')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable(); // a nyers beérkezett kérés törzse, diagnosztikához
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['error_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_webhook_failures');
    }
};
