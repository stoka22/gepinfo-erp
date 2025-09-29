<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use App\Models\Pulse;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DevicesStatusTable extends BaseWidget
{
    protected static ?string $heading = 'Eszközök állapota (utolsó 5 perc)';

    public function table(Table $table): Table
    {
        $since = now()->subMinutes(5);

        // 5 perces impulzus-összeg eszközönként
        $sub5m = Pulse::query()
            ->select('device_id', DB::raw('SUM(delta) as pulses_5m'))
            ->where('sample_time', '>=', $since)
            ->groupBy('device_id');

        // FONTOS: online állapothoz a devices.last_seen_at mezőt használjuk (nem a pulses-ből számoltat)
        $query = Device::query()
            ->with('machine')
            ->leftJoinSub($sub5m, 'p5', 'p5.device_id', '=', 'devices.id')
            ->select('devices.*', DB::raw('COALESCE(p5.pulses_5m,0) as pulses_5m'));

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Eszköz')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('machine.name')
                    ->label('Gép')
                    ->toggleable()
                    ->placeholder('—')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_online_flag')
                    ->label('Státusz')
                    ->boolean()
                    ->state(fn ($record) => $record->last_seen_at?->gte($since))
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Utolsó jel')
                    ->since()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('pulses_5m')
                    ->label('Imp. (5m)')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Nincs eszköz vagy nincs adat.');
    }
}
