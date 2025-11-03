<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardResource\Pages;
use App\Models\Card;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class CardResource extends Resource
{
    protected static ?string $model = Card::class;
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Kártyák';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('uid')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('label')->maxLength(120),
            Forms\Components\Select::make('status')
                ->options(['available'=>'Szabad','assigned'=>'Hozzárendelve','lost'=>'Elveszett','blocked'=>'Blokkolt'])
                ->required(),
            Forms\Components\Textarea::make('notes')->rows(3),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uid')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('employee.name')->label('Dolgozó')->toggleable(),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime()->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCards::route('/'),
            'create' => Pages\CreateCard::route('/create'),
            'edit' => Pages\EditCard::route('/{record}/edit'),
        ];
    }
}
