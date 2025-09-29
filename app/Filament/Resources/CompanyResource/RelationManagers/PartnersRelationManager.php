<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class PartnersRelationManager extends RelationManager
{
    protected static string $relationship = 'partners';
    protected static ?string $title = 'Partnerek';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Név')->required(),
            Forms\Components\TextInput::make('tax_id')->label('Adószám'),
            Forms\Components\Toggle::make('is_supplier')->label('Beszállító'),
            Forms\Components\Toggle::make('is_customer')->label('Vevő')->default(true),
        ])->columns(2);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable(),
                Tables\Columns\IconColumn::make('is_supplier')->label('Beszállító')->boolean(),
                Tables\Columns\IconColumn::make('is_customer')->label('Vevő')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name','tax_id']),
                Tables\Actions\CreateAction::make(), // új partner és automatikus hozzárendelés a céghez
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}
