<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Employee;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Felhasználók';
    protected static ?string $pluralModelLabel = 'Felhasználók';
    protected static ?string $modelLabel = 'Felhasználó';
    protected static ?string $navigationGroup = 'Törzsadatok';

    public static function shouldRegisterNavigation(): bool
    {
        $u = Auth::user();
        // Csak admin (vagy akinek külön engedélye van):
        return $u?->hasRole('admin') || $u?->can('manage users') || $u?->can('access admin panel');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alap adatok')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('company_id')
                    ->label('Cég')
                    ->relationship('company', 'name')  // User::company -> Company::name
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('— Válassz céget —'),

                // Laravel 11-ben a User modellben 'password' => 'hashed' cast van,
                // ezért NEM hash-elünk itt külön.
                Forms\Components\TextInput::make('password')
                    ->label('Jelszó')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn ($state) => $state)         // nincs Hash::make
                    ->dehydrated(fn ($state) => filled($state))          // csak ha meg van adva
                    ->required(fn (string $operation) => $operation === 'create')
                    ->maxLength(255),
            

            Forms\Components\Select::make('employee_link_id')
                    ->label('Dolgozói adatlap (ha már létezik)')
                    ->options(function (?User $record) {
                        // Csak a szabad (account_user_id IS NULL) + a már ehhez a userhez kötött rekord
                        $q = \App\Models\Employee::query()
                            ->orderBy('name')
                            ->selectRaw("id, CONCAT(name, ' — ', COALESCE(position, '')) as label")
                            ->where(function ($w) use ($record) {
                                $w->whereNull('account_user_id');
                                if ($record?->id) {
                                    $w->orWhere('account_user_id', $record->id); // <<< RÉGI user_id HELYETT
                                }
                            });

                        return $q->pluck('label', 'id');
                    })
                    ->getOptionLabelUsing(fn ($value) => \App\Models\Employee::find($value)?->name)
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->dehydrated(false) // NEM kerül a users táblába
                    
                    ->afterStateHydrated(function (callable $set, ?\App\Models\User $record) {
                        // <<< ITT történik a visszatöltés szerkesztéskor
                        $set('employee_link_id', $record?->employee?->id); // User::employee => hasOne via account_user_id
                    })
                    ->hint('Válaszd ki, ha az Employee már létezik és össze akarod kötni.')
            ])->columns(2),

            Forms\Components\Section::make('Szerepkörök és jogosultságok')->schema([
                Forms\Components\Select::make('roles')
                    ->label('Szerepkörök')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),

                Forms\Components\Select::make('permissions')
                    ->label('Egyedi jogosultságok')
                    ->relationship('permissions', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ])->collapsible()->columns(2),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Cég')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Szerepkörök')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Szerepkör')
                    ->relationship('roles', 'name'),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Cég')
                    ->relationship('company', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => Auth::id() !== $record->id), // ne tudd törölni magad
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => Auth::user()?->hasRole('admin')),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
