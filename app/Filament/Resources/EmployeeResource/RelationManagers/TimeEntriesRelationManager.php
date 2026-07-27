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

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('employee_id')
                ->default(fn() => $this->getOwnerRecord()?->getKey())
                ->dehydrated(),
            Forms\Components\Hidden::make('requested_by')
                ->default(fn() => Auth::id())
                ->dehydrated(),

            Forms\Components\Select::make('type')->label('Típus')->options([
                TimeEntryType::Vacation->value  => 'Szabadság',
                TimeEntryType::Overtime->value  => 'Túlóra',
                TimeEntryType::SickLeave->value => 'Táppénz',
                TimeEntryType::UnauthorizedAbsence->value => 'Igazolatlan távollét',
            ])->required()->live(),
            Forms\Components\DatePicker::make('start_date')->label('Dátum')->required(),
            Forms\Components\DatePicker::make('start_time')->label('Kezdet')->required(),
            Forms\Components\DatePicker::make('end_time')->label('Vége')
                ->visible(fn(Forms\Get $get) => $get('type') !== TimeEntryType::Overtime->value)
                ->afterOrEqual('start_date'),

            Forms\Components\TextInput::make('hours')->label('Órák')
                ->numeric()->minValue(0.25)->step(0.25)
                ->visible(fn(Forms\Get $get) => $get('type') === TimeEntryType::Overtime->value),

            Forms\Components\Select::make('status')->label('Státusz')->options([
                TimeEntryStatus::Pending->value  => 'Függőben',
                TimeEntryStatus::Approved->value => 'Jóváhagyva',
                TimeEntryStatus::Rejected->value => 'Elutasítva',
            ])->default(TimeEntryStatus::Pending->value)->required(),

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
                    ? $q->where('employee_id', $ownerId)->latest('start_date')
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
        if (($data['type'] ?? null) === TimeEntryType::Overtime->value) {
            $data['end_date'] = null;
        } else {
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