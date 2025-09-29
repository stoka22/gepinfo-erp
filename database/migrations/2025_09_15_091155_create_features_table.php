// database/migrations/2025_09_15_000001_create_features_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();         // pl. 'partners', 'warehouse'
            $table->string('name');                  // emberi név
            $table->string('group')->nullable();     // menü csoport (pl. 'Raktár')
            $table->text('description')->nullable();
            $table->boolean('is_enabled_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('features'); }
};
