<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';
    protected static ?string $title = 'Funkciók';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')->label('Kulcs')->disabled(),
            Forms\Components\TextInput::make('name')->label('Név')->disabled(),
            Forms\Components\Toggle::make('pivot.enabled')->label('Engedélyezve')->default(true),
            Forms\Components\KeyValue::make('pivot.value')->label('Extra érték')->nullable(),
            Forms\Components\DateTimePicker::make('pivot.starts_at')->label('Érvényesség kezdete')->nullable(),
            Forms\Components\DateTimePicker::make('pivot.ends_at')->label('Érvényesség vége')->nullable(),
        ])->columns(2);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('Kulcs')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable(),
                Tables\Columns\IconColumn::make('pivot.enabled')->label('Aktív')->boolean(),
            ])
            ->headerActions([
                // Attach: meglévő feature hozzárendelése ehhez a céghez
                Tables\Actions\AttachAction::make()
                    ->recordSelectSearchColumns(['key','name'])
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('enabled')->label('Engedélyezve')->default(true),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),   // pivot mezők szerkesztése
                Tables\Actions\DetachAction::make(), // leválasztás
            ]);
    }
}
