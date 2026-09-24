<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_channels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('channel'); // 1..4
            $t->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $t->string('label')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();

            $t->unique(['device_id', 'channel']);
            $t->index('machine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_channels');
    }
};
