<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use App\Models\Item;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class BomComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'bomComponents';
    protected static ?string $title = 'BOM (komponensek)';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('component_item_id')
                ->label('Komponens tétel')
                ->relationship(name: 'component', titleAttribute: 'name')
                ->options(fn() => Item::query()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->whereIn('kind', ['alapanyag','alkatresz'])
                    ->orderBy('name')->pluck('name','id')->all()
                )
                ->searchable()->preload()->required(),
            Forms\Components\TextInput::make('qty_per_unit')->numeric()->label('Mennyiség/egység')->required()->default(1),
            Forms\Components\TextInput::make('note')->label('Megjegyzés')->maxLength(255),
        ])->columns(2);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('component.name')->label('Komponens'),
                Tables\Columns\TextColumn::make('qty_per_unit')->label('Menny./egys.'),
                Tables\Columns\TextColumn::make('component.unit')->label('Egység'),
            ])
            ->headerActions([ Tables\Actions\CreateAction::make() ])
            ->actions([ Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make() ]);
    }
}
