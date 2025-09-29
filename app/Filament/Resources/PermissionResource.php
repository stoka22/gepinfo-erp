<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;

class PermissionResource extends Resource
{
    protected static ?string $model = \Spatie\Permission\Models\Permission::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Jogosultságok';
    protected static ?string $navigationGroup = 'Felhasználók';

    public static function shouldRegisterNavigation(): bool
    {
        $u = Auth::user();
        return $u?->hasRole('admin') || $u?->can('manage users');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Név')
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('guard_name')
                ->label('Guard')
                ->default('web')
                ->required(),

            Forms\Components\Select::make('roles')
                ->label('Szerepkörök')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable(),
        ])->columns(2);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Szerepkörök')
                    ->badge()
                    ->limitList(3),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->hasRole('admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit'   => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
