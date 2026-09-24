<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A pulses tábla eddig két generációt kevert: a régi egycsatornás
// (sample_id/count/delta) és az új, valódi eszköz-API által írt
// négycsatornás (d1..d4) mezőket. Élő hardver sosem írta a régi mezőket
// (csak a pulses:generate teszt-parancs) -- biztonságos a tiszta törlés.
//
// A `uniq_device_minute` (device_id, created_at) unique index (2025_09_01)
// szintén a régi generátor sajátossága volt: az MINDIG explicit
// created_at=$now (a perc-bucket értéke) mellett, percenként EGYSZER, cron
// alól futott -- így (device_id, created_at) a gyakorlatban tényleg egyedi
// volt. A valódi DevicePushController viszont a backlog-újrajátszás miatt
// egyetlen kérésen belül TÖBB Pulse-sort is beszúrhat (minden bejegyzés a
// SAJÁT sample_time-jával, de Eloquent auto-generált created_at-jával,
// ami ugyanabba a valós másodpercbe eshet) -- emiatt ez a constraint
// csendben elhasalna (lásd Pulse::updateOrCreate() try/catch-e) minden
// olyan push-nál, ami backlogot is hoz. A valódi egyediségi garanciát a
// (device_id, sample_time) unique index adja (add_channel_total_to_pulses),
// ez a régi indexre már nincs szükség.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pulses', function (Blueprint $t) {
            $t->dropUnique(['device_id', 'sample_id']);
            $t->dropUnique('uniq_device_minute');
            $t->dropColumn(['sample_id', 'count', 'delta']);
        });
    }

    public function down(): void
    {
        Schema::table('pulses', function (Blueprint $t) {
            $t->unsignedBigInteger('sample_id')->nullable()->index();
            $t->unsignedBigInteger('count')->nullable();
            $t->unsignedBigInteger('delta')->nullable();
            $t->unique(['device_id', 'created_at'], 'uniq_device_minute');
        });
    }
};
