<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_inquiries', function (Blueprint $table) {
            $table->id();
            // Kiemelt mezők közvetlen oszlopként, hogy az admin listában kereshető/
            // látható legyen gyors áttekintésre — a kérdőív összes többi válasza
            // az 'answers' JSON oszlopban van (lásd App\Support\WebsiteInquiryQuestionnaire).
            $table->string('cegnev')->nullable();
            $table->string('kapcsolattarto_neve')->nullable();
            $table->string('telefon')->nullable();
            $table->string('email')->nullable();
            $table->json('answers');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_inquiries');
    }
};
