<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PulseResource\Pages;
use App\Models\Pulse;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A pulse-ok eszköz-generált telemetria, nem admin-szerkeszthető adat --
 * mint az Energy projektnél a reading-eknek sincs create/edit UI-ja -- ezért
 * csak lista+törlés marad. Egy eszköz 4 csatornája (d1-d4) mostantól akár
 * külön-külön géphez is tartozhat (device_channels), ezért minden csatorna-
 * oszlop mellett feltüntetjük, melyik géphez van éppen rendelve.
 */
class PulseResource extends Resource
{
    protected static ?string $model = Pulse::class;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Eszköz impulzusok';
    protected static ?string $modelLabel      = 'Impulzus';
    protected static ?string $pluralLabel     = 'Eszköz impulzusok';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('device.channels.machine'))
            ->columns([
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Eszköz')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sample_time')
                    ->label('Időpont')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('d1_delta')
                    ->label('d1 Δ')
                    ->numeric()
                    ->sortable()
                    ->description(fn (Pulse $r) => $r->device?->machineForChannel(1)?->name ?? '—'),
                Tables\Columns\TextColumn::make('d2_delta')
                    ->label('d2 Δ')
                    ->numeric()
                    ->sortable()
                    ->description(fn (Pulse $r) => $r->device?->machineForChannel(2)?->name ?? '—'),
                Tables\Columns\TextColumn::make('d3_delta')
                    ->label('d3 Δ')
                    ->numeric()
                    ->sortable()
                    ->description(fn (Pulse $r) => $r->device?->machineForChannel(3)?->name ?? '—'),
                Tables\Columns\TextColumn::make('d4_delta')
                    ->label('d4 Δ')
                    ->numeric()
                    ->sortable()
                    ->description(fn (Pulse $r) => $r->device?->machineForChannel(4)?->name ?? '—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Rögzítve')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sample_time', 'desc')
            ->filters([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPulses::route('/'),
        ];
    }
}
