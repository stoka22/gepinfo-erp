<?php
// app/Filament/Resources/PartnerResource/RelationManagers/LocationsRelationManager.php
namespace App\Filament\Resources\PartnerResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $title = 'Telephelyek';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Megnevezés'),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('country')->label('Ország')->maxLength(64),
                Forms\Components\TextInput::make('zip')->label('Irányítószám')->maxLength(16),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('city')->label('Város')->maxLength(128),
                Forms\Components\TextInput::make('street')->label('Utca, házszám')->maxLength(255),
            ]),
            Forms\Components\Fieldset::make('Kapcsolat')->schema([
                Forms\Components\TextInput::make('contact_name')->label('Kapcsolattartó'),
                Forms\Components\TextInput::make('contact_phone')->label('Telefon'),
                Forms\Components\TextInput::make('contact_email')->label('Email')->email(),
            ]),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Megnevezés')->searchable(),
            Tables\Columns\TextColumn::make('zip')->label('Irsz.')->toggleable(),
            Tables\Columns\TextColumn::make('city')->label('Város')->searchable(),
            Tables\Columns\TextColumn::make('street')->label('Cím')->toggleable(),
            Tables\Columns\TextColumn::make('contact_name')->label('Kapcsolattartó')->toggleable(),
            Tables\Columns\TextColumn::make('contact_phone')->label('Telefon')->toggleable(),
            Tables\Columns\TextColumn::make('contact_email')->label('Email')->toggleable(),
        ])
        ->headerActions([ Tables\Actions\CreateAction::make() ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}
