<?php
// database/migrations/2025_09_11_000005_create_partner_locations_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partner_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();     // Telephely megnevezése
            $table->string('country')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('partner_locations'); }
};
