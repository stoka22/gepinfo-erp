<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Törzsadatok';
    protected static ?string $navigationLabel = 'Raktárak';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
            ->default(fn () => Filament::auth()->user()?->company_id)
            ->dehydrated(true),
            
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('code')->label('Kód')->required()->maxLength(32),
                Forms\Components\TextInput::make('name')->label('Név')->required(),
            ]),
            Forms\Components\TextInput::make('location')->label('Helyszín')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('code')->label('Kód')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('location')->label('Helyszín')->toggleable(),
            ])
            ->actions([ Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make() ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $u = Filament::auth()->user();
        if ($u?->isAdmin()) return $q;
        if ($u?->company_id) return $q->where('company_id', $u->company_id);
        return $q->whereRaw('1=0');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit'   => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
