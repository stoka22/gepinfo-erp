<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            // Ha van machines tábla és szeretnéd FK-val: $t->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            // Most egyszerűség kedvéért csak sima oszlop:
            $t->unsignedBigInteger('machine_id')->nullable();

            $t->string('name');
            $t->datetime('starts_at');
            $t->datetime('ends_at')->nullable();
            $t->unsignedInteger('setup_minutes')->default(0);

            $t->timestamps();

            $t->index(['machine_id', 'starts_at']);
            $t->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
