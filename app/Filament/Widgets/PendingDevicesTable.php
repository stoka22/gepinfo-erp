<?php

namespace App\Filament\Widgets;

use App\Models\PendingDevice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingDevicesTable extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(
                PendingDevice::query()->latest('last_seen_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('mac_address')->label('MAC')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('proposed_name')->label('Javasolt név')->toggleable(),
                Tables\Columns\TextColumn::make('fw_version')->label('FW')->toggleable(),
                Tables\Columns\TextColumn::make('ip')->label('IP')->toggleable(),
                Tables\Columns\TextColumn::make('last_seen_at')->label('Utolsó jel')->dateTime()->since(),
            ])
            ->emptyStateHeading('Nincs új eszköz')
            ->paginated([10, 25, 50]);
    }
}
