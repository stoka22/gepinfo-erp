<?php
// database/migrations/2025_09_11_000004_create_company_partner_pivot.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('company_partner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id','partner_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('company_partner'); }
};
