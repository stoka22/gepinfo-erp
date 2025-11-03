<?php

namespace App\Filament\Resources\CardImportResource\RelationManagers;

use App\Models\Employee;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';
    protected static ?string $title = 'Import sorok';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('raw_name')->label('Név'),
            Forms\Components\TextInput::make('raw_uid')->label('UID')->required(),
            Forms\Components\TextInput::make('raw_company')->label('Cég'),
            Forms\Components\Select::make('matched_employee_id')
                ->label('Dolgozó')
                ->options(Employee::orderBy('name')->pluck('name','id')->toArray())
                ->searchable(),
            Forms\Components\TextInput::make('match_score')->label('Pontszám')->numeric()->minValue(0)->maxValue(100)->step(1),
            Forms\Components\Select::make('status')->label('Státusz')->options([
                'new' => 'Új',
                'auto' => 'Automatikus',
                'ambiguous' => 'Kétes',
                'linked' => 'Összekapcsolva',
                'skipped' => 'Kihagyva',
                'duplicate' => 'Duplikált',
            ]),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('raw_name')->label('Név')->searchable(),
                Tables\Columns\TextColumn::make('raw_uid')->label('UID')->searchable(),
                Tables\Columns\TextColumn::make('raw_company')->label('Cég')->toggleable(),
                Tables\Columns\TextColumn::make('matchedEmployee.name')->label('Kapcsolt dolgozó')->searchable(),
                Tables\Columns\TextColumn::make('match_score')->label('Pontszám')->numeric()->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Státusz')
                    ->colors([
                        'gray'    => 'new',
                        'info'    => 'ambiguous',
                        'success' => ['auto', 'linked'], // több állapothoz ugyanaz a szín
                        'warning' => 'skipped',
                        'danger'  => 'duplicate',
                    ])
                    ->formatStateUsing(function ($state) {
                        // ha enum, vegyük az értékét
                        $v = $state instanceof \BackedEnum ? $state->value : $state;

                        return [
                            'new'        => 'Új',
                            'auto'       => 'Automatikus',
                            'ambiguous'  => 'Kétes',
                            'linked'     => 'Összekapcsolva',
                            'skipped'    => 'Kihagyva',
                            'duplicate'  => 'Duplikált',
                        ][$v] ?? (string) $v;
                    })

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'new' => 'Új',
                        'auto' => 'Automatikus',
                        'ambiguous' => 'Kétes',
                        'linked' => 'Összekapcsolva',
                        'skipped' => 'Kihagyva',
                        'duplicate' => 'Duplikált',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Új sor'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('setLinked')
                    ->label('Jelölés összekapcsoltként')
                    ->action(fn ($records) => collect($records)->each->update(['status' => 'linked'])),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
