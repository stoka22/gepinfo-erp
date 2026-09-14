<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A user_agent és referrer oszlopok eddig VARCHAR(191) hosszúságúak voltak
     * (az AppServiceProvider-ben beállított Schema::defaultStringLength(191)
     * miatt, mivel a migráció nem adott meg explicit hosszt). Ez asztali gépen
     * nem tűnt fel, mert a rövidebb desktop User-Agent stringek beleférnek, de
     * a hosszabb mobil/appon belüli böngésző User-Agent stringek (pl. a
     * Facebookon belüli böngésző Androidon) simán túllépik a 191 karaktert,
     * ami MySQL strict módban "Data too long for column 'user_agent'" hibát és
     * 500-as szervererrort okoz. TEXT oszlopra váltva ez a hibaosztály
     * megszűnik.
     */
    public function up(): void
    {
        Schema::table('page_visits', function (Blueprint $table) {
            $table->text('user_agent')->nullable()->change();
            $table->text('referrer')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_visits', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->change();
            $table->string('referrer')->nullable()->change();
        });
    }
};
