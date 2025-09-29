<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PulseResource\Pages;
use App\Filament\Resources\PulseResource\RelationManagers;
use App\Models\Pulse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PulseResource extends Resource
{
    protected static ?string $model = Pulse::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('device_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('sample_id')
                    ->required()
                    ->numeric(),
                Forms\Components\DateTimePicker::make('sample_time')
                    ->required(),
                Forms\Components\TextInput::make('count')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('delta')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sample_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sample_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delta')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'create' => Pages\CreatePulse::route('/create'),
            'edit' => Pages\EditPulse::route('/{record}/edit'),
        ];
    }
}
