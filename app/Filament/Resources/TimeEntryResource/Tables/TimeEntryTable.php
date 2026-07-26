<?php

namespace App\Filament\Resources\TimeEntryResource\Tables;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TimeEntryTable
{
    public static function configure(Tables\Table $table): Tables\Table
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

                Tables\Columns\IconColumn::make('needs_review')
                    ->label('Felülvizsgálandó')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('')
                    ->tooltip(fn (TimeEntry $r) => $r->needs_review ? 'Automatikus kiléptetés – ellenőrizd az időpontot, majd hagyd jóvá.' : null)
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
                Tables\Actions\Action::make('reviewAutoCheckout')
                    ->label('Jóváhagyás')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Automatikus (12 órás) kiléptetés. Ha az időpont nem pontos, előbb szerkeszd, majd hagyd jóvá, hogy a túlóra-keret elszámolásra kerüljön.')
                    ->visible(fn (TimeEntry $r) => $r->needs_review && Auth::user()->can('update', $r))
                    ->action(function (TimeEntry $r) {
                        $r->needs_review = false;
                        $r->save();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (TimeEntry $r) => Auth::user()->can('update', $r)),

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
}
