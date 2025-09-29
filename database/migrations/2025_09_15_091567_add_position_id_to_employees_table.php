<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Opcionális: régi string 'position' oszlopból betöltés, ha létezett
        if (Schema::hasColumn('employees', 'position')) {
            // párosítjuk a dolgozó tulaj (users) cégét, és oda létrehozzuk/kapcsoljuk a pozíciót
            DB::statement("
                INSERT IGNORE INTO positions (company_id, name, active, created_at, updated_at)
                SELECT DISTINCT u.company_id, e.position, 1, NOW(), NOW()
                FROM employees e
                JOIN users u ON u.id = e.user_id
                WHERE e.position IS NOT NULL AND e.position <> '' 
            ");

            DB::statement("
                UPDATE employees e
                JOIN users u ON u.id = e.user_id
                JOIN positions p ON p.company_id = u.company_id AND p.name = e.position
                SET e.position_id = p.id
                WHERE e.position_id IS NULL AND e.position IS NOT NULL AND e.position <> ''
            ");
        }
    }
    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
