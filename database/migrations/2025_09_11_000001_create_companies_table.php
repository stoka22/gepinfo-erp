<?php
// database/migrations/2025_09_11_000001_create_companies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            // opcionális csoportosítás (1..3) admin-szűrőhöz
            $table->unsignedTinyInteger('group')->nullable()->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('companies'); }
};
