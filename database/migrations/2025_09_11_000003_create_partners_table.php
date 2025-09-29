<?php
// database/migrations/2025_09_11_000003_create_partners_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('tax_id')->nullable()->index(); // adószám
            // a létrehozó/“tulaj” cég (nem kizárólagos láthatóság!)
            $table->foreignId('owner_company_id')->nullable()->constrained('companies')->nullOnDelete();
            // típusok: lehet egyszerre mindkettő
            $table->boolean('is_supplier')->default(false)->index(); // beszállító
            $table->boolean('is_customer')->default(true)->index();  // vevő
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('partners'); }
};
