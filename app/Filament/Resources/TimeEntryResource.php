<?php

namespace App\Filament\Resources;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Filament\Resources\TimeEntryResource\Pages;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Employee;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Szabadság / Túlóra / Táppénz';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([

                // ⬇️ Céghez kötés – automatikus kitöltés
                Forms\Components\Hidden::make('company_id')
                    ->default(fn () => Auth::user()?->company_id)
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make('employee_id')
                    ->label('Dolgozó')
                    ->options(function () {
                        $cid = Auth::user()?->company_id;

                        $q = Employee::query()
                            ->select('employees.id', 'employees.name')
                            ->orderBy('employees.name');

                        if ($cid) {
                            $q->join('users', 'users.id', '=', 'employees.user_id')
                            ->where('users.company_id', $cid);
                        }

                        return $q->pluck('employees.name', 'employees.id');
                    })
                    ->preload()
                    ->searchable()
                    ->required(),

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

                Forms\Components\Select::make('status')
                    ->label('Státusz')
                    ->options([
                        TimeEntryStatus::Pending->value  => 'Függőben',
                        TimeEntryStatus::Approved->value => 'Jóváhagyva',
                        TimeEntryStatus::Rejected->value => 'Elutasítva',
                    ])
                    ->default(TimeEntryStatus::Pending->value)
                    ->required(),

                Forms\Components\Textarea::make('note')
                    ->label('Megjegyzés')
                    ->rows(3),

                Forms\Components\Hidden::make('requested_by')
                    ->default(fn () => Auth::id()),
            ])->columns(2),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Dolgozó')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Típus')
                    ->color(fn (string|\BackedEnum|null $state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'vacation'   => 'warning',
                        'overtime'   => 'info',
                        'sick_leave' => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string|\BackedEnum|null $state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'vacation'   => 'Szabadság',
                        'overtime'   => 'Túlóra',
                        'sick_leave' => 'Táppénz',
                        default      => (string) ($state instanceof \BackedEnum ? $state->value : $state),
                    }),

                Tables\Columns\TextColumn::make('start_date')->date()->label('Kezdet')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->label('Vége')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('hours')->numeric(2)->label('Órák')->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Státusz')
                    ->color(fn (string|\BackedEnum|null $state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'pending'  => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string|\BackedEnum|null $state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'pending'  => 'Függőben',
                        'approved' => 'Jóváhagyva',
                        'rejected' => 'Elutasítva',
                        default    => (string) ($state instanceof \BackedEnum ? $state->value : $state),
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Típus')->options([
                    'vacation'   => 'Szabadság',
                    'overtime'   => 'Túlóra',
                    'sick_leave' => 'Táppénz',
                ]),
                Tables\Filters\SelectFilter::make('status')->label('Státusz')->options([
                    'pending'  => 'Függőben',
                    'approved' => 'Jóváhagyva',
                    'rejected' => 'Elutasítva',
                ]),
                Tables\Filters\Filter::make('month')
                    ->label('Hónap')
                    ->form([
                        Forms\Components\DatePicker::make('month')
                            ->label('Hónap')
                            ->native(false)
                            ->displayFormat('Y-m'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['month'])) {
                            return $query;
                        }
                        $dt = Carbon::parse($data['month']);
                        return $query->whereMonth('start_date', $dt->month)
                                     ->whereYear('start_date',  $dt->year);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Jóváhagy')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (TimeEntry $r) => Auth::user()->can('approve', $r) && $r->status->value === 'pending')
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Approved;
                        $r->approved_by = Auth::id();
                        $r->save();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Elutasít')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (TimeEntry $r) => Auth::user()->can('approve', $r) && $r->status->value === 'pending')
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Rejected;
                        $r->approved_by = Auth::id();
                        $r->save();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveSelected')
                    ->label('Kijelöltek jóváhagyása')
                    ->action(function ($records) {
                        foreach ($records as $r) {
                            if (Auth::user()->can('approve', $r) && $r->status->value === 'pending') {
                                $r->update([
                                    'status' => TimeEntryStatus::Approved,
                                    'approved_by' => Auth::id(),
                                ]);
                            }
                        }
                    })
                    ->requiresConfirmation()
                    ->icon('heroicon-o-check'),
            ])
            ->defaultSort('start_date', 'desc');
    }

    // ⬇️ Céges szűrés minden lekérdezésre
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();

        if (Auth::check() && Auth::user()->company_id) {
            $q->where('company_id', Auth::user()->company_id);
        }

        return $q;
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
