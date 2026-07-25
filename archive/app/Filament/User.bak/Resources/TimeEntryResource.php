<?php

namespace App\Filament\User\Resources;

use App\Enums\TimeEntryType;
use App\Enums\TimeEntryStatus;
use App\Filament\User\Resources\TimeEntryResource\Pages;
use App\Models\Employee;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Időnyilvántartás';

    public static function getEloquentQuery(): Builder
    {
        // csak saját dolgozók bejegyzései
        return parent::getEloquentQuery()->whereHas('employee', fn ($q) => $q->where('user_id', auth()->id()));
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Dolgozó')
                    ->relationship(
                        name: 'employee',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($q) => $q->where('user_id', auth()->id())
                    )
                    ->required()
                    ->preload()
                    ->searchable(),

                Forms\Components\Select::make('type')
                    ->label('Típus')
                    ->options([
                        TimeEntryType::Vacation->value  => 'Szabadság',
                        TimeEntryType::Overtime->value  => 'Túlóra',
                        TimeEntryType::SickLeave->value => 'Táppénz',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Kezdet')
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Vége')
                    ->visible(fn (Forms\Get $get) => $get('type') !== TimeEntryType::Overtime->value)
                    ->afterOrEqual('start_date'),

                Forms\Components\TextInput::make('hours')
                    ->label('Órák')
                    ->numeric()
                    ->minValue(0.25)
                    ->step(0.25)
                    ->visible(fn (Forms\Get $get) => $get('type') === TimeEntryType::Overtime->value),

                Forms\Components\Textarea::make('note')->label('Megjegyzés')->rows(3),

                Forms\Components\Hidden::make('status')
                    ->default(TimeEntryStatus::Pending->value),

                Forms\Components\Hidden::make('requested_by')
                    ->default(fn () => auth()->id()),
            ])->columns(2),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')->label('Dolgozó')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Típus')
                    ->formatStateUsing(fn ($s) => ['vacation'=>'Szabadság','overtime'=>'Túlóra','sick_leave'=>'Táppénz'][$s] ?? $s),
                Tables\Columns\TextColumn::make('start_date')->date()->label('Kezdet')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->label('Vége')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('hours')->numeric(2)->label('Órák')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Státusz')
                    ->colors([
                        'gray'    => TimeEntryStatus::Pending->value,
                        'success' => TimeEntryStatus::Approved->value,
                        'danger'  => TimeEntryStatus::Rejected->value,
                    ])
                    ->formatStateUsing(fn ($s) => ['pending'=>'Függőben','approved'=>'Jóváhagyva','rejected'=>'Elutasítva'][$s] ?? $s),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Típus')->options([
                    'vacation'=>'Szabadság','overtime'=>'Túlóra','sick_leave'=>'Táppénz'
                ]),
                Tables\Filters\SelectFilter::make('status')->label('Státusz')->options([
                    'pending'=>'Függőben','approved'=>'Jóváhagyva','rejected'=>'Elutasítva'
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (TimeEntry $r) => $r->status->value === 'pending'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (TimeEntry $r) => $r->status->value === 'pending'),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTimeEntries::route('/'),
            'create' => Pages\CreateTimeEntry::route('/create'),
            'edit'   => Pages\EditTimeEntry::route('/{record}/edit'),
        ];
    }
}
