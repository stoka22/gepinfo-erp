<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Skill;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Dolgozóim';

    public static function getEloquentQuery(): Builder
    {
        // csak a saját dolgozók
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alap adatok')->schema([
                Forms\Components\TextInput::make('name')->required()->label('Név'),
                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\TextInput::make('position')->label('Pozíció'),
                Forms\Components\DatePicker::make('hired_at')->label('Felvétel dátuma'),
            ])->columns(2),

            Forms\Components\Section::make('Skill-ek')->schema([
                Forms\Components\Repeater::make('skillsRepeater')
                    ->relationship('skills')
                    ->schema([
                        Forms\Components\Select::make('skill_id')
                            ->label('Skill')
                            ->options(fn() => Skill::query()->orderBy('name')->pluck('name','id'))
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('pivot.level')
                            ->numeric()->minValue(0)->maxValue(5)
                            ->label('Szint (0-5)')->default(0)->required(),
                        Forms\Components\DatePicker::make('pivot.certified_at')->label('Vizsga dátuma'),
                        Forms\Components\Textarea::make('pivot.notes')->rows(2)->label('Megjegyzés'),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->orderable(false),
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('position')->label('Pozíció')->sortable(),
                Tables\Columns\TextColumn::make('skills.name')->badge()->label('Skill-ek'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // nincs Delete a user panelen
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
