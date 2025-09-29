<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\PartnersRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\FeaturesRelationManager;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Cégek';
    protected static ?string $pluralLabel = 'Cégek';
    protected static ?string $modelLabel = 'Cég';
    protected static ?string $navigationGroup = 'Törzsadatok';

    public static function shouldRegisterNavigation(): bool
    {
      return Filament::auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Név')->required()->maxLength(255),
                Forms\Components\Select::make('group')
                    ->label('Csoport')->options([1=>'1',2=>'2',3=>'3'])->nullable(),
            ])->columns(2),

            // ÚJ: felhasználók hozzárendelése létrehozáskor
            Forms\Components\Section::make('Felhasználók hozzárendelése')
                ->description('A kiválasztott felhasználók company_id-je erre a cégre áll be.')
                ->schema([
                    Forms\Components\Select::make('users_to_attach')
                        ->label('Felhasználók')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        // ha csak a még nem rendelt usereket szeretnéd: ->options(User::whereNull('company_id')->orderBy('name')->pluck('name','id')->all())
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->dehydrated(false) // ne próbálja a Company modellre menteni
                        ->hidden(fn () => ! (Filament::auth()->user()?->isAdmin())),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('group')->label('Csoport')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Felhasználók')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('partners_count')->counts('partners')->label('Partnerek')->badge()->color('primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')->label('Csoport')->options([1=>'1',2=>'2',3=>'3']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if ($user?->isAdmin()) return $query;
        if ($user?->company_id) return $query->whereKey($user->company_id);
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
