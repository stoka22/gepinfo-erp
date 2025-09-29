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
            Forms\Components\CheckboxList::make('days')
            ->label('Érvényes napok')
            ->options([
                0=>'Vasárnap', 1=>'Hétfő', 2=>'Kedd', 3=>'Szerda',
                4=>'Csütörtök', 5=>'Péntek', 6=>'Szombat',
            ])
            ->columns(4)
            ->default([1,2,3,4,5]) // H–P
            // a 'days' virtuális attribútumot a modell get/set kezeli -> days_mask
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
                ->formatStateUsing(function ($state, $record) {
                    $map = ['Vas','Hét','Ked','Sze','Csü','Pén','Szo'];
                    $out = [];
                    for ($i=0;$i<=6;$i++){
                        if (($record->days_mask & (1<<$i))!==0) $out[] = $map[$i];
                    }
                    return implode(', ', $out);
                }),
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
