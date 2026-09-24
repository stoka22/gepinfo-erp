<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Egy eszköz 4 bemeneti csatornája (d1-d4) egyenként más-más géphez is
 * rendelhető -- a Device::booted()::created hook mindig pontosan 4 sort
 * seedel (channel=1..4), ezért itt nincs create/delete, csak szerkesztés.
 */
class ChannelsRelationManager extends RelationManager
{
    protected static string $relationship = 'channels';
    protected static ?string $title = 'Csatornák (d1-d4)';
    protected static ?string $recordTitleAttribute = 'channel';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('machine_id')
                ->label('Gép')
                ->relationship('machine', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('label')
                ->label('Címke')
                ->placeholder('pl. érzékelő helye/típusa')
                ->maxLength(255),
            Forms\Components\Toggle::make('active')
                ->label('Aktív')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->label('Csatorna')
                    ->formatStateUsing(fn (int $state) => "d{$state}")
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('machine.name')
                    ->label('Gép')
                    ->placeholder('— nincs hozzárendelve —')
                    ->badge(),
                Tables\Columns\TextColumn::make('label')->label('Címke')->toggleable(),
                Tables\Columns\IconColumn::make('active')->label('Aktív')->boolean(),
            ])
            ->defaultSort('channel')
            ->actions([
                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),
            ])
            ->bulkActions([]);
    }
}
