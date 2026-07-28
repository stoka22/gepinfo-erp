<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\TimeEntry;
use Filament\Tables\Table;
use App\Enums\TimeEntryType;
use App\Enums\TimeEntryStatus;
//use Tables\Columns\DateColumn;
use Illuminate\Support\Carbon;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\DateColumn;
use Filament\Notifications\Notification;
use PhpOffice\PhpSpreadsheet\Writer\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;



class TimeEntriesRelationManager extends RelationManager
{

    protected static string $relationship = 'timeEntries';
    protected static string $slug = 'time-entries';
    protected static ?string $title = 'Szabadságok / Túlórák / Táppénz';


    // (opcionális) egyértelműsítjük a modellt
    public function getModel(): string
    {
        return TimeEntry::class;
    }

    /** presence típushoz: hours számítása és status beállítása (ld. TimeEntryResource\Forms\TimeEntryForm). */
    private static function recalcPresence(Forms\Set $set, Forms\Get $get): void
    {
        $type = $get('type') instanceof \BackedEnum ? $get('type')->value : $get('type');
        if ($type !== TimeEntryType::Presence->value) {
            return;
        }

        $sd = $get('start_date');
        $st = $get('start_time');
        $ed = $get('end_date') ?: $sd;
        $et = $get('end_time');

        if (! $sd || ! $st) {
            $set('hours', null);
            return;
        }

        $in  = Carbon::parse("{$sd} " . ($st ?: '00:00'));
        $out = $et ? Carbon::parse("{$ed} {$et}") : null;

        if ($out && $out->lessThan($in)) {
            $out->addDay();
        }

        if ($out) {
            $minutes = max(0, $in->diffInMinutes($out));
            $set('hours', round($minutes / 60, 2));
            $set('status', TimeEntryStatus::CheckedOut->value);
        } else {
            $set('hours', 0.00);
            $set('status', TimeEntryStatus::CheckedIn->value);
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('employee_id')
                ->default(fn() => $this->getOwnerRecord()?->getKey())
                ->dehydrated(),
            Forms\Components\Hidden::make('requested_by')
                ->default(fn() => Auth::id())
                ->dehydrated(),

            Forms\Components\Select::make('type')->label('Típus')
                ->options([
                    TimeEntryType::Presence->value  => 'Jelenlét',
                    TimeEntryType::Vacation->value  => 'Szabadság',
                    TimeEntryType::Overtime->value  => 'Túlóra',
                    TimeEntryType::SickLeave->value => 'Táppénz',
                    TimeEntryType::UnauthorizedAbsence->value => 'Igazolatlan távollét',
                ])
                ->default(TimeEntryType::Presence->value)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                    $type = $state instanceof \BackedEnum ? $state->value : $state;

                    if ($type === TimeEntryType::Presence->value) {
                        $set('status', TimeEntryStatus::CheckedIn->value);
                    } else {
                        $set('status', TimeEntryStatus::Pending->value);
                        $set('start_time', null);
                        $set('end_time', null);

                        if ($type !== TimeEntryType::Overtime->value) {
                            $set('hours', null);
                        }
                    }

                    static::recalcPresence($set, $get);
                }),

            Forms\Components\DatePicker::make('start_date')
                ->label('Dátum')
                ->required()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::recalcPresence($set, $get)),

            Forms\Components\DatePicker::make('end_date')
                ->label('Vég dátum')
                ->visible(fn (Forms\Get $get) =>
                    ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) !== TimeEntryType::Overtime->value
                    && ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) !== TimeEntryType::Presence->value
                )
                ->afterOrEqual('start_date')
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::recalcPresence($set, $get)),

            Forms\Components\TimePicker::make('start_time')
                ->label('Belépés ideje')
                ->seconds(false)
                ->minutesStep(5)
                ->visible(fn (Forms\Get $get) => ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === TimeEntryType::Presence->value)
                ->required(fn (Forms\Get $get) => ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === TimeEntryType::Presence->value)
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::recalcPresence($set, $get)),

            Forms\Components\TimePicker::make('end_time')
                ->label('Kilépés ideje')
                ->seconds(false)
                ->minutesStep(5)
                ->visible(fn (Forms\Get $get) => ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === TimeEntryType::Presence->value)
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::recalcPresence($set, $get)),

            Forms\Components\TextInput::make('hours')->label('Órák')
                ->numeric()->minValue(0.25)->step(0.25)
                ->dehydrated(true)
                ->visible(fn(Forms\Get $get) => $get('type') === TimeEntryType::Overtime->value),

            Forms\Components\Select::make('status')
                ->label(fn (Forms\Get $get) => $get('type') === TimeEntryType::Presence->value ? 'Jelenlét státusz' : 'Jóváhagyási státusz')
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

            Forms\Components\Textarea::make('note')->label('Megjegyzés')->rows(3),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            // ⬇️ LÉNYEG: explicit query, owner hiányakor "üres" lekérdezés
            ->query(function (): Builder {
                $ownerId = $this->getOwnerRecord()?->getKey();

                $q = TimeEntry::query();
                return $ownerId
                    // Felülvizsgálandó sorok mindig legelöl.
                    ? $q->where('employee_id', $ownerId)->orderByDesc('needs_review')->orderByDesc('start_date')
                    : $q->whereRaw('1 = 0'); // sosem ad vissza rekordot, de Builder NEM null
            })
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Típus')

                    ->color(function ($state) {
                        $val = $state instanceof \BackedEnum
                            ? $state->value
                            : ($state instanceof \UnitEnum ? $state->name : $state);

                        return match ($val) {
                            'vacation'  => 'warning',
                            'overtime'  => 'info',
                            'sick_leave' => 'danger',
                            'unauthorized_absence' => 'danger',
                            'regular'   => 'info',
                            'presence'   => 'green',
                            default     => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        $val = $state instanceof \BackedEnum
                            ? $state->value
                            : ($state instanceof \UnitEnum ? $state->name : $state);

                        return match ($val) {
                            'vacation'   => 'Szabadság',
                            'overtime'   => 'Túlóra',
                            'sick_leave' => 'Táppénz',
                            'unauthorized_absence' => 'Igazolatlan távollét',
                            'regular'    => 'Munka',
                            'presence'   => 'Jelenlét',
                            default      => (string) $val,
                        };
                    }),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Dátum')
                    ->date('Y-m-d')
                    ->sortable(query: fn(\Illuminate\Database\Eloquent\Builder $q, string $direction) => $q->orderBy('time_entries.start_date', $direction))
                ,

                Tables\Columns\TextColumn::make('start_time')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i:s'))
                    ->label('Kezdet')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : '—')
                    ->label('Vége')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hours')->numeric(2)->label('Órák')->placeholder('—'),
                Tables\Columns\TextColumn::make('location')->label('Helyszín')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')->label('Státusz')
                    ->color(function ($state) {
                        $val = $state instanceof \BackedEnum ? $state->value : $state;
                        return match ($val) {
                            'pending' => 'gray',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'open', 'checked_in' => 'info',
                            'checked_out' => 'success',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        $val = $state instanceof \BackedEnum ? $state->value : $state;
                        return match ($val) {
                            'pending' => 'Függőben',
                            'approved' => 'Jóváhagyva',
                            'rejected' => 'Elutasítva',
                            'open' => 'Nyitva',
                            'checked_in' => 'Bejelentkezve',
                            'checked_out' => 'Kijelentkezve',
                            default => (string) $val,
                        };
                    }),

                Tables\Columns\IconColumn::make('needs_review')
                    ->label('Felülvizsgálandó')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon(''),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->label('Hónap')
                    ->options([
                        '01' => 'Január',
                        '02' => 'Február',
                        '03' => 'Március',
                        '04' => 'Április',
                        '05' => 'Május',
                        '06' => 'Június',
                        '07' => 'Július',
                        '08' => 'Augusztus',
                        '09' => 'Szeptember',
                        '10' => 'Október',
                        '11' => 'November',
                        '12' => 'December',
                    ])


                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            $query->whereMonth('start_date', $data['value']);
                        }
                        return $query;
                    }),
            ])
            ->headerActions([
                
                Action::make('export_pdf')
    ->label('Export PDF')
    ->icon('heroicon-o-document')
    ->color('warning')
    ->action(function () {
        // Lekérjük a szűrt rekordokat
        $entries = $this->getFilteredTableQuery()
            ->orderBy('start_date', 'desc')
            ->get();

        $employeeName = auth()->user()->name ?? 'Ismeretlen';
        $employerName = 'Cég neve Kft.';
        $filterDescription = $this->getActiveFiltersDescription();

        // Szűrt hónap lekérése
        $activeFilters = $this->getTableFilters();
        $selectedMonth = null;
        foreach ($activeFilters as $filter) {
            if ($filter->getName() === 'month' && $filter->isActive()) {
                $state = $filter->getState();
                if (is_array($state) && isset($state['value'])) {
                    $selectedMonth = $state['value'];
                }
            }
        }

        // Hónapnév meghatározása
        $monthNames = [
            '01' => 'Január', '02' => 'Február', '03' => 'Március',
            '04' => 'Április', '05' => 'Május', '06' => 'Június',
            '07' => 'Július', '08' => 'Augusztus', '09' => 'Szeptember',
            '10' => 'Október', '11' => 'November', '12' => 'December' ];
        
       
        $monthLabel = $selectedMonth
            ? $monthNames[$selectedMonth]
            : \Carbon\Carbon::parse($startDate)->locale('hu')->monthName;



        // Hónap napjai
        $monthStart = Carbon::parse($startDate)->startOfMonth();
        $monthEnd = Carbon::parse($startDate)->endOfMonth();
        $daysInMonth = [];
        $currentDay = $monthStart->copy();
        while ($currentDay->lte($monthEnd)) {
            $daysInMonth[] = [
                'date' => $currentDay->format('Y-m-d'),
                'dayName' => $currentDay->locale('hu')->dayName,
            ];
            $currentDay->addDay();
        }

        // HTML nézet renderelése
        $html = view('exports.time_entries', [
            'entries' => $entries,
            'daysInMonth' => $daysInMonth,
            'employeeName' => $employeeName,
            'employerName' => $employerName,
            'filterDescription' => $filterDescription,
            'monthLabel' => $monthLabel, // ÚJ
        ])->render();

        // PDF generálás
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Oldalszám hozzáadása
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text = "Oldal $pageNumber / $pageCount";
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $size = 10;
            $canvas->text(520, 820, $text, $font, $size);
        });

        return response()->streamDownload(fn() => print($dompdf->output()), 'jelenleti_adatok.pdf');
    }),
        Tables\Actions\Action::make('export')
                    ->label('Exportálás')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    
                    ->action(function () {
                            (new \App\Exports\TimeEntriesExport())->download();
                        })
                    ,

                Tables\Actions\CreateAction::make()
            ])
            ->actions([
                Tables\Actions\Action::make('reviewAutoCheckout')
                    ->label('')
                    ->tooltip('Jóváhagyás (automatikus kiléptetés ellenőrzése)')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Automatikus (12 órás) kiléptetés vagy hiányos be-/kilépés. Ha az időpont nem pontos, előbb szerkeszd, majd hagyd jóvá, hogy a túlóra-keret elszámolásra kerüljön.')
                    ->visible(fn (TimeEntry $r) => $r->needs_review && Auth::user()?->can('update', $r))
                    ->action(function (TimeEntry $r) {
                        $r->needs_review = false;
                        $r->save();
                        Notification::make()->title('Felülvizsgálva')->success()->send();
                    }),
                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),
                Tables\Actions\Action::make('approve')->label('')->tooltip('Jóváhagyás')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn(TimeEntry $r) => Auth::user()?->can('approve', $r) && ($r->status->value ?? $r->status) === 'pending')
                    ->requiresConfirmation()
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Approved;
                        $r->approved_by = Auth::id();
                        $r->save();
                        Notification::make()->title('Jóváhagyva')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')->label('')->tooltip('Elutasít')->icon('heroicon-o-x-circle')
                    ->visible(fn(TimeEntry $r) => Auth::user()?->can('approve', $r) && ($r->status->value ?? $r->status) === 'pending')
                    ->requiresConfirmation()
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Rejected;
                        $r->approved_by = Auth::id();
                        $r->save();
                        Notification::make()->title('Elutasítva')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] ?? null;

        if ($type === TimeEntryType::Overtime->value) {
            $data['end_date'] = null;
        } elseif ($type !== TimeEntryType::Presence->value) {
            // Presence-nél a hours-t a recalcPresence számolja/a TimeEntryObserver véglegesíti.
            $data['hours'] = null;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateFormDataBeforeCreate($data);
    }

    protected function getActiveFiltersDescription(): string
    {
        $filters = $this->getTableFilters();
        $active = [];

        foreach ($filters as $filter) {
            if ($filter->isActive()) {
                $state = $filter->getState();

                // Ha nem tömb, alakítsd tömbbé
                if (!is_array($state)) {
                    $state = [$state];
                }

                $active[] = $filter->getLabel() . ': ' . implode(', ', array_filter($state));
            }
        }

        return $active ? implode(' | ', $active) : 'Nincs szűrés';
    }

}