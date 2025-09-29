<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';
    protected static ?string $title = 'Felhasználók';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Név')->required(),
            Forms\Components\TextInput::make('email')->label('Email')->email()->required(),
            Forms\Components\TextInput::make('password')
                ->label('Jelszó')->password()
                ->dehydrateStateUsing(fn ($state) => $state ? bcrypt($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->revealable(),
            Forms\Components\Select::make('role')->label('Szerepkör')
                ->options(['admin' => 'admin', 'user' => 'user'])->required(),
            Forms\Components\Select::make('group')->label('Csoport')
                ->options([1=>'1',2=>'2',3=>'3'])->nullable(),
        ])->columns(2);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('role')->label('Szerepkör')->badge(),
                Tables\Columns\TextColumn::make('group')->label('Csoport'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->id; // a jelenlegi céghez rendeljük
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
