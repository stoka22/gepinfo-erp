<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftPatternResource\Pages;
use App\Models\ShiftPattern;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftPatternResource extends Resource
{
    protected static ?string $model = ShiftPattern::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
   // protected static ?string $navigationGroup = 'Tervezés';
   protected static ?string $navigationGroup = 'Termelés';
    protected static ?string $label = 'Műszak minta';
    protected static ?string $pluralLabel = 'Műszak minták';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Név')->required(),
            Forms\Components\CheckboxList::make('days_mask')   // ← ugyanaz a név, mint a DB oszlop
                ->label('Napok')
                ->options(\App\Models\ShiftPattern::dayMap())
                ->columns(3)

                // DB -> űrlap (int -> tömb)
                ->afterStateHydrated(function ($component, $state) {
                    $mask = (int) ($state ?? 0);
                    $selected = [];
                    foreach (\App\Models\ShiftPattern::dayMap() as $bit => $label) {
                        if (($mask & (int)$bit) === (int)$bit) {
                            $selected[] = (string)$bit;
                        }
                    }
                    $component->state($selected);
                })

                // űrlap -> DB (tömb -> int)
                ->dehydrateStateUsing(function ($state) {
                    return array_reduce($state ?? [], fn ($sum, $bit) => $sum + (int)$bit, 0);
                })
                ->dehydrated(true),
                
            Forms\Components\TimePicker::make('start_time')
                ->label('Műszak kezdete')->seconds(false)->required(),

            Forms\Components\TimePicker::make('end_time')
                ->label('Műszak vége')->seconds(false)->required()
                ->helperText('Ha a vége kisebb, mint a kezdés, a minta átlóg másnapra.'),
            Forms\Components\Repeater::make('breaks')
                ->label('Műszak közbeni szünetek')
                ->relationship() // ShiftPattern::breaks
                ->orderable(false)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Megnevezés')->maxLength(100),
                    Forms\Components\TimePicker::make('start_time')->label('Kezdés')->seconds(false)->required(),
                    Forms\Components\TextInput::make('duration_min')->label('Időtartam (perc)')
                        ->numeric()->minValue(1)->required(),
                ])
                ->createItemButtonLabel('Szünet hozzáadása')
                ->columns(3),
         ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Név')->searchable(),
             Tables\Columns\TextColumn::make('days_mask')
                ->label('Napok')
                ->getStateUsing(fn (\App\Models\ShiftPattern $record) => $record->days_label)
                ,
            Tables\Columns\TextColumn::make('start_time')->label('Kezdés'),
            Tables\Columns\TextColumn::make('end_time')->label('Vége'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftPatterns::route('/'),
            'create' => Pages\CreateShiftPattern::route('/create'),
            'edit' => Pages\EditShiftPattern::route('/{record}/edit'),
        ];
    }
}
