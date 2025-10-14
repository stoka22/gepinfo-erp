<?php

namespace App\Filament\Resources;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Filament\Resources\TimeEntryResource\Pages;
use App\Models\TimeEntry;
use App\Models\Employee;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
                        TimeEntryType::Presence->value  => 'Jelenlét',
                        TimeEntryType::Vacation->value  => 'Szabadság',
                        TimeEntryType::Overtime->value  => 'Túlóra',
                        TimeEntryType::SickLeave->value => 'Táppénz',
                    ])
                    ->default(TimeEntryType::Presence->value)
                    ->required()
                    ->live(),

                // ⬇ EGY mező: a type-tól függően tölti fel az opciókat
                Forms\Components\Select::make('status')
                    ->label(fn (Forms\Get $get) => $get('type') === TimeEntryType::Presence->value
                        ? 'Jelenlét státusz'
                        : 'Jóváhagyási státusz')
                    ->options(function (Forms\Get $get) {
                        return $get('type') === TimeEntryType::Presence->value
                            ? [
                                TimeEntryStatus::CheckedIn->value  => 'Bejelentkezve',
                                TimeEntryStatus::CheckedOut->value => 'Kijelentkezve',
                              ]
                            : [
                                TimeEntryStatus::Pending->value  => 'Függőben',
                                TimeEntryStatus::Approved->value => 'Jóváhagyva',
                                TimeEntryStatus::Rejected->value => 'Elutasítva',
                              ];
                    })
                    ->default(fn (Forms\Get $get) => $get('type') === TimeEntryType::Presence->value
                        ? TimeEntryStatus::CheckedIn->value
                        : TimeEntryStatus::Pending->value)
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Kezdet')
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Vége')
                    ->visible(fn (Forms\Get $get) =>
                        $get('type') !== TimeEntryType::Overtime->value
                        && $get('type') !== TimeEntryType::Presence->value)
                    ->afterOrEqual('start_date'),

                Forms\Components\TextInput::make('hours')
                    ->label('Órák')
                    ->numeric()
                    ->minValue(0.25)
                    ->step(0.25)
                    ->visible(fn (Forms\Get $get) => $get('type') === TimeEntryType::Overtime->value),

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
            // opcionális: műszak szerinti bal szegély színezés – maradhat, ha használod
            ->recordClasses(function (TimeEntry $record) {
                $shift = optional($record->employee)->shift ?? null;
                $v = $shift instanceof \BackedEnum ? $shift->value : $shift;
                return match ($v) {
                    'morning'   => 'border-l-4 border-l-amber-500/70',
                    'afternoon' => 'border-l-4 border-l-emerald-500/70',
                    'night'     => 'border-l-4 border-l-indigo-500/70',
                    default     => 'border-l-4 border-l-slate-500/40',
                };
            })
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Dolgozó')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Típus')
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'presence'   => 'primary',
                        'vacation'   => 'warning',
                        'overtime'   => 'info',
                        'sick_leave' => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'presence'   => 'Jelenlét',
                        'vacation'   => 'Szabadság',
                        'overtime'   => 'Túlóra',
                        'sick_leave' => 'Táppénz',
                        default      => (string) ($state instanceof \BackedEnum ? $state->value : $state),
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('start_date')->date()->label('Kezdet')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('end_date')->date()->label('Vége')->sortable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('hours')->numeric(2)->label('Órák')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),

                // ⬇ Egyetlen status oszlop — mindkét domain-t kezeli
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Státusz')
                    ->color(function ($state, TimeEntry $r) {
                        $v = $state instanceof \BackedEnum ? $state->value : $state;
                        if (($r->type instanceof \BackedEnum ? $r->type->value : $r->type) === 'presence') {
                            return match ($v) {
                                'checked_in'  => 'success',
                                'checked_out' => 'gray',
                                default       => 'gray',
                            };
                        }
                        return match ($v) {
                            'pending'  => 'gray',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default    => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state, TimeEntry $r) {
                        $v = $state instanceof \BackedEnum ? $state->value : $state;
                        if (($r->type instanceof \BackedEnum ? $r->type->value : $r->type) === 'presence') {
                            return match ($v) {
                                'checked_in'  => 'Bejelentkezve',
                                'checked_out' => 'Kijelentkezve',
                                default       => '—',
                            };
                        }
                        return match ($v) {
                            'pending'  => 'Függőben',
                            'approved' => 'Jóváhagyva',
                            'rejected' => 'Elutasítva',
                            default    => (string) $v,
                        };
                    })
                    ->toggleable(),
            ])
            ->filters([
                // TÍPUS-kapcsoló – Presence alapból NINCS a listában → rejtve indul
                Tables\Filters\Filter::make('types_visible')
                    ->label('Megjelenő típusok')
                    ->form([
                        Forms\Components\CheckboxList::make('types')
                            ->options([
                                TimeEntryType::Presence->value  => 'Jelenlét',
                                TimeEntryType::Vacation->value  => 'Szabadság',
                                TimeEntryType::Overtime->value  => 'Túlóra',
                                TimeEntryType::SickLeave->value => 'Táppénz',
                            ])
                            ->default([
                                TimeEntryType::Vacation->value,
                                TimeEntryType::Overtime->value,
                                TimeEntryType::SickLeave->value,
                                // Presence kimarad → alapból rejtve
                            ])
                            ->columns(4),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $selected = $data['types'] ?? [];
                        if (empty($selected)) return $query->whereRaw('1=0');
                        return $query->whereIn('type', $selected);
                    })
                    ->indicateUsing(fn (array $data) => empty($data['types']) ? '0 típus' : count($data['types']).' típus'),

                // Egységes státusz szűrő: mindkét domain opcióival
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->multiple()
                    ->options([
                        // jelenlét
                        TimeEntryStatus::CheckedIn->value  => 'Bejelentkezve',
                        TimeEntryStatus::CheckedOut->value => 'Kijelentkezve',
                        // jóváhagyás
                        TimeEntryStatus::Pending->value  => 'Függőben',
                        TimeEntryStatus::Approved->value => 'Jóváhagyva',
                        TimeEntryStatus::Rejected->value => 'Elutasítva',
                    ]),

                // Hónap szűrő (marad)
                Tables\Filters\Filter::make('month')
                    ->label('Hónap')
                    ->form([
                        Forms\Components\DatePicker::make('month')
                            ->label('Hónap')
                            ->native(false)
                            ->displayFormat('Y-m'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['month'])) return $query;
                        $dt = Carbon::parse($data['month']);
                        return $query->whereMonth('start_date', $dt->month)
                                     ->whereYear('start_date',  $dt->year);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Jóváhagyás/elutasítás csak akkor releváns, ha NEM jelenlét a típus
                Tables\Actions\Action::make('approve')
                    ->label('Jóváhagy')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (TimeEntry $r) =>
                        ($r->type->value ?? $r->type) !== 'presence'
                        && ($r->status->value ?? $r->status) === 'pending'
                        && Auth::user()->can('approve', $r)
                    )
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Approved;
                        $r->approved_by = Auth::id();
                        $r->save();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Elutasít')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (TimeEntry $r) =>
                        ($r->type->value ?? $r->type) !== 'presence'
                        && ($r->status->value ?? $r->status) === 'pending'
                        && Auth::user()->can('approve', $r)
                    )
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
                            if (
                                ($r->type->value ?? $r->type) !== 'presence' &&
                                Auth::user()->can('approve', $r) &&
                                ($r->status->value ?? $r->status) === 'pending'
                            ) {
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
