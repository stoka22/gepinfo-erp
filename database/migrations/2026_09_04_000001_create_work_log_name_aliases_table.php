<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A munkanapló-import automatikus dolgozó-párosítása kizárólag az employees.name
        // pontos (kis-/nagybetűtől független) egyezésén alapul. Ha valaki utólag becenevet
        // fűz egy dolgozó nevéhez (pl. "Kiss-B. Vendelné" -> "Kiss-B. Vendelné / Niki /"),
        // a nyers exportban szereplő eredeti név onnantól MINDEN további importnál elbukik,
        // és kézzel újra hozzá kell rendelni — élesben azonosítva (Kiss-Balázs Vendelné,
        // 2026-08-28/31). Ez a tábla emlékezik a nyers export-név -> dolgozó párosításra,
        // FÜGGETLENÜL attól, mi van épp az employees.name mezőben.
        Schema::create('work_log_name_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('nev'); // az eredeti, nem normalizált export-név (referenciának)
            $table->string('nev_key')->unique(); // mb_strtolower(trim(nev)) — ez alapján keresünk
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_log_name_aliases');
    }
};
