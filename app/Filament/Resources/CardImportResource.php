<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardImportResource\Pages;
use App\Models\CardImport;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CardImportResource extends Resource
{
    protected static ?string $model = CardImport::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Kártya importok';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('source')->label('Forrásfájl')->searchable(),
                Tables\Columns\TextColumn::make('rows_count')
                    ->label('Sorok')
                    ->state(fn (CardImport $r) => $r->rows()->count()),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Létrehozva')->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Import törlése')
                    ->modalHeading('Import és sorainak törlése')
                    ->modalDescription('A kiválasztott import összes staging sorát is töröljük.')
                    ->before(function (CardImport $record) {
                        // ha a FK nincs cascade-on, itt töröljük kézzel
                        $record->rows()->delete();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            CardImportResource\RelationManagers\RowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCardImports::route('/'),
            'view'  => Pages\ViewCardImport::route('/{record}'),
        ];
    }
}
