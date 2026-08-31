<?php

namespace App\Filament\Resources\TimeEntryResource\Tables;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Notifications\Notification;
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
                        'unauthorized_absence' => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'presence'   => 'Jelenlét',
                        'vacation'   => 'Szabadság',
                        'overtime'   => 'Túlóra',
                        'sick_leave' => 'Táppénz',
                        'unauthorized_absence' => 'Igazolatlan távollét',
                        default      => (string) ($state instanceof \BackedEnum ? $state->value : $state),
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Kezdet')
                    ->sortable()
                    ->toggleable()
                    ->formatStateUsing(function ($state, TimeEntry $record) {
                        $date = $state?->format('Y-m-d');
                        $isPresence = ($record->type instanceof \BackedEnum ? $record->type->value : $record->type) === 'presence';
                        $time = $isPresence ? ($record->raw_start_time ?? $record->start_time) : null;
                        return $time ? "{$date} {$time->format('H:i')}" : $date;
                    }),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Vége')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->formatStateUsing(function ($state, TimeEntry $record) {
                        if ($state === null) {
                            return null;
                        }
                        $date = $state->format('Y-m-d');
                        $isPresence = ($record->type instanceof \BackedEnum ? $record->type->value : $record->type) === 'presence';
                        $time = $isPresence ? $record->end_time : null;
                        return $time ? "{$date} {$time->format('H:i')}" : $date;
                    }),
                Tables\Columns\TextColumn::make('hours')->numeric(2)->label('Órák')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('location')->label('Helyszín')->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),

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

                // Mennyi ideje vár egy felülvizsgálandó bejegyzés — enélkül könnyen elsüllyed
                // egy régi tétel a listában; rendezhető, hogy a legrégebbiek előre kerüljenek.
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Várakozik')
                    ->formatStateUsing(fn (TimeEntry $r) => $r->needs_review ? $r->created_at?->diffForHumans(null, true).' óta' : null)
                    ->badge()
                    ->color(fn (TimeEntry $r) => $r->needs_review && $r->created_at?->lt(now()->subDays(3)) ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                // Közvetlen, önálló kapcsoló a felülvizsgálandó sorokra (a vezérlőpult
                // "Felülvizsgálandó" KPI-csempéje erre linkel) — a types_visible szűrőtől
                // függetlenül, hogy egy kattintással izolálható legyen a teljes lista.
                Tables\Filters\Filter::make('only_needs_review')
                    ->label('Csak felülvizsgálandó')
                    ->query(fn (Builder $query) => $query->where('needs_review', true)),

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
                                TimeEntryType::UnauthorizedAbsence->value => 'Igazolatlan távollét',
                            ])
                            ->default([
                                TimeEntryType::Vacation->value,
                                TimeEntryType::Overtime->value,
                                TimeEntryType::SickLeave->value,
                                TimeEntryType::UnauthorizedAbsence->value,
                                // Presence kimarad → alapból rejtve
                            ])
                            ->columns(4),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $selected = $data['types'] ?? [];
                        // A felülvizsgálandó sorok a típus-szűrőtől függetlenül mindig látszanak.
                        return $query->where(function (Builder $q) use ($selected) {
                            if (! empty($selected)) {
                                $q->whereIn('type', $selected);
                            }
                            $q->orWhere('needs_review', true);
                        });
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
                    ->label('')
                    ->tooltip('Jóváhagyás')
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
                    ->label('')
                    ->tooltip('Szerkesztés')
                    ->visible(fn (TimeEntry $r) => Auth::user()->can('update', $r)),

                // Jóváhagyás/elutasítás csak akkor releváns, ha NEM jelenlét a típus
                Tables\Actions\Action::make('approve')
                    ->label('')
                    ->tooltip('Jóváhagy')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
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
                    ->label('')
                    ->tooltip('Elutasít')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
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

                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Törlés'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveSelected')
                    ->label('Kijelöltek jóváhagyása')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $approvedCount = 0;
                        $reviewedCount = 0;
                        $skippedCount = 0;

                        foreach ($records as $r) {
                            $type = $r->type->value ?? $r->type;

                            // A jelenlét-bejegyzéseknél nincs "jóváhagyás" (pending/approved)
                            // állapot, a soronkénti megfelelője a "felülvizsgálandó" (needs_review)
                            // jelzés feloldása -- enélkül ez a tömeges művelet a kijelölt jelenlét-
                            // sorokat eddig csendben, VISSZAJELZÉS NÉLKÜL kihagyta, "nem történik
                            // semmi" érzetét keltve, ha valaki több felülvizsgálandó jelenlét-sort
                            // jelölt ki egyszerre (a soronkénti "Jóváhagyás" gomb ugyanezt teszi).
                            if ($type === TimeEntryType::Presence->value) {
                                if ($r->needs_review && Auth::user()->can('update', $r)) {
                                    $r->needs_review = false;
                                    $r->save();
                                    $reviewedCount++;
                                } else {
                                    $skippedCount++;
                                }
                                continue;
                            }

                            if (
                                Auth::user()->can('approve', $r) &&
                                ($r->status->value ?? $r->status) === 'pending'
                            ) {
                                $r->update([
                                    'status' => TimeEntryStatus::Approved,
                                    'approved_by' => Auth::id(),
                                ]);
                                $approvedCount++;
                            } else {
                                $skippedCount++;
                            }
                        }

                        $parts = [];
                        if ($approvedCount) $parts[] = "{$approvedCount} jóváhagyva";
                        if ($reviewedCount) $parts[] = "{$reviewedCount} felülvizsgálat feloldva";
                        if ($skippedCount) $parts[] = "{$skippedCount} kihagyva (nem jóváhagyható/felülvizsgálandó állapotú)";

                        Notification::make()
                            ->title($parts ? implode(', ', $parts) : 'Nem volt jóváhagyható/felülvizsgálandó tétel a kijelöltek között.')
                            ->color(($approvedCount || $reviewedCount) ? 'success' : 'warning')
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->icon('heroicon-o-check'),
            ])
            ->defaultSort('start_date', 'desc');
    }
}
