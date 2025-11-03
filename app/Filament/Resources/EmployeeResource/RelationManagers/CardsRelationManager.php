<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class CardsRelationManager extends RelationManager
{
    protected static string $relationship = 'cards';
    protected static ?string $recordTitleAttribute = 'card_uid';
    protected static ?string $title = 'Belépőkártyák';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('card_uid')
                ->label('Kártya azonosító (UID/QR)')
                ->required()
                ->maxLength(191),
            Forms\Components\TextInput::make('label')
                ->label('Megnevezés')
                ->maxLength(100),
            Forms\Components\TextInput::make('type')
                ->label('Típus')
                ->placeholder('mifare, em4100, qr, ...')
                ->maxLength(50),
            Forms\Components\Toggle::make('active')
                ->label('Aktív')
                ->default(true),
            Forms\Components\DateTimePicker::make('assigned_at')
                ->label('Hozzárendelve'),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('card_uid')->label('Kártya azonosító')->searchable(),
                Tables\Columns\TextColumn::make('label')->label('Megnevezés')->toggleable(),
                Tables\Columns\TextColumn::make('type')->label('Típus')->toggleable(),
                Tables\Columns\IconColumn::make('active')->boolean()->label('Aktív'),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime()->label('Hozzárendelve'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
