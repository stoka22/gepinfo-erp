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

        // 5 perces impulzus-összeg eszközönként
        $sub5m = Pulse::query()
            ->select('device_id', DB::raw('SUM(delta) as pulses_5m'))
            ->where('sample_time', '>=', $since)
            ->groupBy('device_id');

        // Gépenként: összes eszköz, online eszközök száma (devices.last_seen_at alapján), 5 perces impulzus összeg
        $query = Machine::query()
            ->leftJoin('devices as d', 'd.machine_id', '=', 'machines.id')
            ->leftJoinSub($sub5m, 'p5', 'p5.device_id', '=', 'd.id')
            ->select('machines.*')
            ->selectRaw('COUNT(d.id) as devices_total')
            ->selectRaw('SUM(CASE WHEN d.last_seen_at >= ? THEN 1 ELSE 0 END) as devices_online', [$since])
            ->selectRaw('COALESCE(SUM(p5.pulses_5m),0) as pulses_5m_total')
            ->groupBy('machines.id');

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
