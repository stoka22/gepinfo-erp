<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class MachinesRelationManager extends RelationManager
{
    protected static string $relationship = 'machines';
    protected static ?string $title = 'Gépek (alkatrész kapcsolatok)';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([]); // csak attach/detach
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Gép neve')->searchable(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
