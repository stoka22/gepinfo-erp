<?php

namespace App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\ProductionSplit;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;

class ProductionSplitResource extends Resource
{
    protected static ?string $model = ProductionSplit::class;
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('partner_order_item_id')
                ->label('Megrendelés sor')
                ->relationship('orderItem', 'item_name_cache')
                ->searchable()->preload()->required(),
            TextInput::make('qty')->numeric()->minValue(0.001)->required(),
            DatePicker::make('produced_at')->default(now()),
            Textarea::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('orderItem.item_name_cache')->label('Tétel'),
            TextColumn::make('qty')->label('Mennyiség'),
            TextColumn::make('produced_at')->date(),
            TextColumn::make('orderItem.order.order_no')->label('Rendelésszám'),
        ]);
    }
}
