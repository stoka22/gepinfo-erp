<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResourceShiftAssignmentResource\Pages;
use App\Models\ResourceShiftAssignment;
use App\Models\Machine;
use App\Models\ShiftPattern;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResourceShiftAssignmentResource extends Resource
{
    protected static ?string $model = ResourceShiftAssignment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    //protected static ?string $navigationGroup = 'Tervezés';
    protected static ?string $navigationGroup = 'Termelés';
    protected static ?string $label = 'Műszak hozzárendelés';
    protected static ?string $pluralLabel = 'Műszak hozzárendelések';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('resource_id')
                ->label('Gép')
                ->options(fn() => Machine::query()->orderBy('name')->pluck('name','id'))
                ->searchable()->required(),

            Forms\Components\Select::make('shift_pattern_id')
                ->label('Műszak minta')
                ->options(function () {
                    $daysMap = ['Vas','Hét','Ked','Sze','Csü','Pén','Szo'];

                    return \App\Models\ShiftPattern::query()
                        ->orderBy('start_time')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(function ($p) use ($daysMap) {
                            $labels = [];
                            for ($i = 0; $i <= 6; $i++) {
                                if (($p->days_mask & (1 << $i)) !== 0) {
                                    $labels[] = $daysMap[$i];
                                }
                            }
                            $daysStr = implode(',', $labels) ?: '—';
                            return [
                                $p->id => "{$p->name} ({$daysStr} {$p->start_time}-{$p->end_time})",
                            ];
                        });
                })
                ->searchable()
                ->required(),


            Forms\Components\DatePicker::make('valid_from')->label('Érvényes ettől')->required(),
            Forms\Components\DatePicker::make('valid_to')->label('Érvényes eddig')->default(null),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('resource.name')->label('Gép')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('pattern.name')->label('Minta')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('pattern.dow')->label('Nap')
                ->formatStateUsing(fn($v)=>['Vas','Hét','Ked','Sze','Csü','Pén','Szo'][$v] ?? $v),
            Tables\Columns\TextColumn::make('pattern.start_time')->label('Kezdés'),
            Tables\Columns\TextColumn::make('pattern.end_time')->label('Vége'),
            Tables\Columns\TextColumn::make('valid_from')->date()->label('Ettől'),
            Tables\Columns\TextColumn::make('valid_to')->date()->label('Eddig'),
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
            'index' => Pages\ListResourceShiftAssignments::route('/'),
            'create' => Pages\CreateResourceShiftAssignment::route('/create'),
            'edit' => Pages\EditResourceShiftAssignment::route('/{record}/edit'),
        ];
    }
}
