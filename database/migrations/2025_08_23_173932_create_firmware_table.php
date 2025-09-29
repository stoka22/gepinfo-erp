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
        Schema::create('firmwares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hardware_code')->nullable();   // pl. „ESP32-WROOM-32E”
            $table->string('version');                     // pl. „1.2.3”
            $table->unsignedInteger('build')->default(1);  // opcionális build szám
            $table->string('file_path');                   // storage path (storage/app/…)
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('sha256')->nullable();
            $table->boolean('forced')->default(false);     // kötelező frissítés?
            $table->timestamp('published_at')->nullable(); // kiadás időpontja (ha null, még draft)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hardware_code', 'version']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firmware');
    }
};
