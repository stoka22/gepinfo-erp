<?php

namespace App\Filament\Widgets;

use App\Models\Machine;
use App\Models\Pulse;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class MachinesHealthTable extends BaseWidget
{
    protected static ?string $heading = 'Gépek – üzem az elmúlt 5 percben';

    public function table(Table $table): Table
{
    $since = now()->subMinutes(5);
    $onlineT = now()->subMinute(); // vagy ami nálad az "online" küszöb

    // 5 perces impulzus eszközönként
    $p5 = Pulse::query()
        ->select('device_id', DB::raw('SUM(delta) as pulses_5m'))
        ->where('sample_time', '>=', $since)
        ->groupBy('device_id');

    // eszközök összesítése gépenként (összes / online)
    $devAgg = DB::table('devices as d')
        ->select(
            'd.machine_id',
            DB::raw('COUNT(*) as devices_total')
        )
        ->selectRaw('SUM(CASE WHEN d.last_seen_at >= ? THEN 1 ELSE 0 END) as devices_online', [$onlineT])
        ->groupBy('d.machine_id');

    // 5 perces impulzusok összesítése gépenként
    $pulseAgg = DB::table('devices as d')
        ->leftJoinSub($p5, 'p5', 'p5.device_id', '=', 'd.id')
        ->select('d.machine_id', DB::raw('COALESCE(SUM(p5.pulses_5m),0) as pulses_5m_total'))
        ->groupBy('d.machine_id');

    // végső lekérdezés: NINCS GROUP BY, NINCS machines.*
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
