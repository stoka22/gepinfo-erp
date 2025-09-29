<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers\MachinesRelationManager;
use App\Filament\Resources\ItemResource\RelationManagers\BomComponentsRelationManager;
use App\Filament\Resources\ItemResource\RelationManagers\WorkStepsRelationManager;
use App\Models\Item;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';

   
    protected static ?string $modelLabel = 'Tétel';
    protected static ?string $navigationGroup = 'Készlet';
    protected static ?string $navigationLabel = 'Tételek';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('sku')->label('Cikkszám')->maxLength(64),
                Forms\Components\TextInput::make('name')->label('Megnevezés')->required(),
                Forms\Components\TextInput::make('unit')->label('Egység')->default('db')->maxLength(16),
                Forms\Components\Select::make('kind')->label('Típus')->required()
                    ->options([
                        'alkatresz'  => 'Alkatrész',
                        'alapanyag'  => 'Alapanyag',
                        'kesztermek' => 'Késztermék',
                    ]),
                Forms\Components\Toggle::make('is_active')->label('Aktív')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $q) {
                // Mindig legyen benne az items.* – különben a join "elveszi"
                $q->select('items.*');

                if (! Schema::hasTable('stock_levels')) {
                    // ha nincs tábla, adjunk konstans 0-t
                    $q->addSelect(DB::raw('0 AS current_stock_sum'));
                    return;
                }

                // 1) előállítjuk az összesítést item + company szerint
                //    (ha nincs company_id az egyik táblában, simán item szerint aggregálunk)
                $groupByCompany = Schema::hasColumn('stock_levels', 'company_id') && Schema::hasColumn('items', 'company_id');

                $sub = DB::table('stock_levels')
                    ->selectRaw(
                        $groupByCompany
                            ? 'item_id, company_id, COALESCE(SUM(qty),0) AS stock_sum'
                            : 'item_id, COALESCE(SUM(qty),0) AS stock_sum'
                    )
                    ->when($groupByCompany, fn($qq) => $qq->groupBy('item_id','company_id'))
                    ->when(!$groupByCompany, fn($qq) => $qq->groupBy('item_id'));

                // 2) LEFT JOIN a részösszegre
                $q->leftJoinSub($sub, 'sl', function ($join) use ($groupByCompany) {
                    $join->on('sl.item_id', '=', 'items.id');
                    if ($groupByCompany) {
                        // ha van company mindkét oldalon, akkor azon is illesztünk
                        $join->on('sl.company_id', '=', 'items.company_id');
                    }
                });

                // 3) az aliasolt készletoszlopot vegyük fel
                $q->addSelect(DB::raw('COALESCE(sl.stock_sum, 0) AS current_stock_sum'));
            })
        ->columns([
            Tables\Columns\TextColumn::make('sku')->label('Cikkszám')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('name')->label('Megnevezés')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('kind')->label('Típus')->badge()
                ->formatStateUsing(fn(string $state) => match($state){
                    'alkatresz'=>'Alkatrész','alapanyag'=>'Alapanyag','kesztermek'=>'Késztermék', default => $state
                }),
            Tables\Columns\TextColumn::make('current_stock_sum')
                ->label('Készlet')
                ->alignRight()
                ->sortable()
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ')),
            Tables\Columns\IconColumn::make('is_active')->label('Aktív')->boolean(),
        ])
        ->filters([
            SelectFilter::make('kind')
                ->label('Típus')
                ->attribute('kind')         // ← explicit
                ->options([
                    'alkatresz'  => 'Alkatrész',
                    'alapanyag'  => 'Alapanyag',
                    'kesztermek' => 'Késztermék',
                ])
                ->multiple()
                ->searchable()
                ->indicator('Típus'),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
            /** @var Builder $q */
        $q = Item::query(); // 100%, hogy Eloquent\Builder és van model

        $u = Filament::auth()->user();
        if ($u?->isAdmin()) {
            return $q;
        }

        if ($u?->company_id) {
            return $q->where('company_id', $u->company_id);
        }

        return $q->whereRaw('1=0');
    }

    public static function getRelations(): array
    {
        return [
            MachinesRelationManager::class,
            BomComponentsRelationManager::class,
            WorkStepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'view'   => Pages\ViewItem::route('/{record}'),
            'edit'   => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
