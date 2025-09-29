<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square';
    
    protected static ?string $navigationGroup = 'Készlet';
    protected static ?string $navigationLabel = 'Bevételezés';
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Fejléc')->schema([
                Forms\Components\TextInput::make('receipt_no')->label('Bizonylatszám')->maxLength(64),
                Forms\Components\DatePicker::make('occurred_at')->label('Dátum')->default(now())->required(),
                Forms\Components\Select::make('warehouse_id')->label('Raktár')->required()
                    ->options(fn() => Warehouse::query()
                        ->where('company_id', Filament::auth()->user()?->company_id)
                        ->orderBy('name')->pluck('name','id')->all())
                    ->searchable()->preload(),
                Forms\Components\Select::make('supplier_partner_id')->label('Beszállító (partner)')
                    ->relationship(name: 'supplier', titleAttribute: 'name')
                    ->searchable()->preload(),
                Forms\Components\TextInput::make('currency')->label('Pénznem')->default('HUF')->maxLength(8),
                Forms\Components\Textarea::make('note')->label('Megjegyzés')->rows(2),
            ])->columns(3),

            Forms\Components\Section::make('Tételek')->schema([
                Forms\Components\Repeater::make('lines')->relationship()->defaultItems(1)
                    ->schema([
                        Forms\Components\Select::make('item_id')->label('Tétel')->required()
                            ->options(fn() => Item::query()
                                ->where('company_id', Filament::auth()->user()?->company_id)
                                ->orderBy('name')->pluck('name','id')->all())
                            ->searchable()->preload(),
                        Forms\Components\TextInput::make('qty')->label('Mennyiség')->numeric()->required()->default(1),
                        Forms\Components\TextInput::make('unit_cost')->label('Egységár')->numeric()->required()->default(0),
                        Forms\Components\TextInput::make('line_total')->label('Összesen (auto)')
                            ->disabled()
                            ->dehydrated(true)
                            ->afterStateUpdated(null)
                            ->formatStateUsing(fn($state, Forms\Get $get) =>
                                number_format(((float)$get('qty')) * ((float)$get('unit_cost')), 2, '.', '')
                            ),
                        Forms\Components\TextInput::make('note')->label('Megj.')->maxLength(255),
                    ])
                    ->columns(4)
                    ->reorderable()
                    ->collapsible(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('receipt_no')->label('Bizonylat')->searchable(),
                Tables\Columns\TextColumn::make('occurred_at')->date()->label('Dátum')->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Raktár'),
                Tables\Columns\TextColumn::make('supplier.name')->label('Beszállító')->toggleable(),
                Tables\Columns\TextColumn::make('posted_at')->dateTime()->label('Könyvelve')->toggleable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->visible(fn($record)=>!$record->posted_at),
                Tables\Actions\DeleteAction::make()->visible(fn($record)=>!$record->posted_at),
            ]);
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
            'index'  => Pages\ListGoodsReceipts::route('/'),
            'create' => Pages\CreateGoodsReceipt::route('/create'),
            'view'   => Pages\ViewGoodsReceipt::route('/{record}'),
            'edit'   => Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
