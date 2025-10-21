<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\PartnersRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\FeaturesRelationManager;
use App\Models\Company;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon  = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Cégek';
    protected static ?string $pluralLabel     = 'Cégek';
    protected static ?string $modelLabel      = 'Cég';
    protected static ?string $navigationGroup = 'Törzsadatok';

    public static function shouldRegisterNavigation(): bool
    {
        $u = Auth::user();
        return (bool) ($u?->hasRole('admin') || $u?->can('companies.viewAny'));
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alap adatok')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required()
                    ->maxLength(255),

                // RÉGI "group" helyett: valódi cégcsoport kapcsolat
                Forms\Components\Select::make('company_group_id')
                    ->label('Cégcsoport')
                    ->relationship('group', 'name') // Company::group()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('— Nincs cégcsoport —'),
            ])->columns(2),

            Forms\Components\Section::make('Felhasználók hozzárendelése')
                ->description('A kiválasztott felhasználók company_id-je erre a cégre áll be.')
                ->schema([
                    Forms\Components\Select::make('users_to_attach')
                        ->label('Felhasználók')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->dehydrated(false)
                        ->hidden(fn () => ! Auth::user()?->can('companies.attachUsers')),
                ]),
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

                // RÉGI "group" oszlop helyett:
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Cégcsoport')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Felhasználók')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('partners_count')
                    ->counts('partners')
                    ->label('Partnerek')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                // RÉGI SelectFilter('group') helyett:
                Tables\Filters\SelectFilter::make('company_group_id')
                    ->label('Cégcsoport')
                    ->relationship('group', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn () => Auth::user()?->can('companies.view')),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->can('companies.update')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->can('companies.delete')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = Auth::user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        if ($user?->company_id) {
            return $query->whereKey($user->company_id);
        }

        return $query->whereRaw('1=0');
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            PartnersRelationManager::class,
            FeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'view'   => Pages\ViewCompany::route('/{record}'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
