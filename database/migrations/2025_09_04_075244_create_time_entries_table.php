<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('type');   // App\Enums\TimeEntryType
            $table->string('status')->default('pending'); // App\Enums\TimeEntryStatus

            $table->date('start_date');        // szabadság/táppénz kezdet vagy túlóra napja
            $table->date('end_date')->nullable(); // szabadság/táppénz vége; túlóránál null

            $table->decimal('hours', 5, 2)->nullable(); // túlórához (pl. 2.50)
            $table->text('note')->nullable();

            $table->foreignId('requested_by')->constrained('users');   // aki rögzítette
            $table->foreignId('approved_by')->nullable()->constrained('users'); // aki jóváhagyta/elutasította

            $table->timestamps();

            $table->index(['employee_id', 'type', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
