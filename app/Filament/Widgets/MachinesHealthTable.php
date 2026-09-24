<?php

namespace App\Filament\Widgets;

use App\Models\Machine;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

/**
 * Gépenkénti összesítés a device_channels táblán keresztül -- egy eszköz
 * 4 csatornája (d1-d4) mostantól akár 4 különböző géphez is tartozhat, ezért
 * a "melyik géphez tartozik" kérdés nem a devices.machine_id-ből, hanem a
 * device_channels.machine_id-ből dől el, csatornánként. Korábban ez a widget
 * a devices.machine_id-n csoportosított és a pulses.delta (legacy,
 * egycsatornás, sosem a valódi eszköz-API által írt) oszlopot összegezte.
 */
class MachinesHealthTable extends BaseWidget
{
    protected static ?string $heading = 'Gépek – üzem az elmúlt 5 percben';

    public function table(Table $table): Table
    {
        $since = now()->subMinutes(5);
        $onlineT = now()->subSeconds((int) config('devices.online_timeout', 60));

        $channelDelta = <<<'SQL'
            CASE dc.channel
                WHEN 1 THEN p.d1_delta WHEN 2 THEN p.d2_delta
                WHEN 3 THEN p.d3_delta WHEN 4 THEN p.d4_delta ELSE 0
            END
        SQL;

        // 5 perces impulzusok gépenként, a csatorna-hozzárendelésen keresztül
        $pulseAgg = DB::table('device_channels as dc')
            ->join('pulses as p', 'p.device_id', '=', 'dc.device_id')
            ->where('dc.active', true)
            ->where('p.sample_time', '>=', $since)
            ->select('dc.machine_id', DB::raw("SUM({$channelDelta}) as pulses_5m_total"))
            ->groupBy('dc.machine_id');

        // Eszközök összesítése gépenként (összes / online) -- egy gép
        // "eszközszáma" mostantól azt jelenti: hány KÜLÖNBÖZŐ eszköznek van
        // legalább egy aktív csatornája erre a gépre állítva.
        $devAgg = DB::table('device_channels as dc')
            ->join('devices as d', 'd.id', '=', 'dc.device_id')
            ->where('dc.active', true)
            ->select('dc.machine_id', DB::raw('COUNT(DISTINCT d.id) as devices_total'))
            ->selectRaw('COUNT(DISTINCT CASE WHEN d.last_seen_at >= ? THEN d.id END) as devices_online', [$onlineT])
            ->groupBy('dc.machine_id');

        $query = Machine::query()
            ->leftJoinSub($devAgg, 'da', 'da.machine_id', '=', 'machines.id')
            ->leftJoinSub($pulseAgg, 'pa', 'pa.machine_id', '=', 'machines.id')
            ->select([
                'machines.id',
                'machines.code',
                'machines.name',
                DB::raw('COALESCE(da.devices_total,0)  as devices_total'),
                DB::raw('COALESCE(da.devices_online,0) as devices_online'),
                DB::raw('COALESCE(pa.pulses_5m_total,0) as pulses_5m_total'),
            ]);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kód')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Gép')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('devices_online')
                    ->label('Online / Össz.')
                    ->formatStateUsing(fn ($state, $record) => "{$record->devices_online} / {$record->devices_total}")
                    ->sortable(),

                Tables\Columns\TextColumn::make('pulses_5m_total')
                    ->label('Imp. (5m)')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('devices_online', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Nincs gép.');
    }
}
