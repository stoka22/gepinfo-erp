<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyGroupResource\Pages;
use App\Models\CompanyGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

class CompanyGroupResource extends Resource
{
    protected static ?string $model = CompanyGroup::class;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Cégcsoportok';
    protected static ?string $modelLabel      = 'Cégcsoport';
    protected static ?string $pluralLabel     = 'Cégcsoportok';
    protected static ?string $navigationGroup = 'Törzsadatok';

    public static function shouldRegisterNavigation(): bool
    {
        $u = Auth::user(); // egyszerű és elég
        return (bool) ($u?->hasRole('admin') || $u?->can('company_groups.viewAny'));
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return (string) static::getEloquentQuery()->count();
        } catch (\Throwable $e) {
            return null; // ha migráció előtt vagyunk
        }
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alap adatok')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required()
                    ->maxLength(255),
            ])->columns(1),
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

                Tables\Columns\TextColumn::make('companies_count')
                    ->counts('companies')
                    ->label('Cégek')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                // ide jöhet később szűrő, ha kell
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->can('company_groups.update') || Auth::user()?->hasRole('admin')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->can('company_groups.delete') || Auth::user()?->hasRole('admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanyGroups::route('/'),
            'create' => Pages\CreateCompanyGroup::route('/create'),
            'edit'   => Pages\EditCompanyGroup::route('/{record}/edit'),
        ];
    }
}
