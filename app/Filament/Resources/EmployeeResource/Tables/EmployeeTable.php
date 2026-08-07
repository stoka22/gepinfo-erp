<?php

namespace App\Filament\Resources\EmployeeResource\Tables;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\Card;
use Filament\Tables;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TimeEntry;
use App\Enums\TimeEntryType;
use App\Enums\TimeEntryStatus;
use App\Services\CardService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Schema;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use App\Filament\Resources\EmployeeResource;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class EmployeeTable
{
    /** Visszaadja [regular_hours, overtime_hours] decimális órában. */
    protected static function splitRegularAndOvertime(float $totalHours): array
    {
        $threshold = 10.5;     // 10 óra 30 perc
        $regularCap = ($totalHours >= $threshold) ? 8.0 : 8.5;
        $regular = min($totalHours, $regularCap);
        $overtime = max(0, $totalHours - $regular);
        return [$regular, $overtime];
    }

    public static function configure(Tables\Table $table): Tables\Table
    {
        $groupId = EmployeeResource::currentGroupId();
        $groupCompanies = Company::query()
            ->when($groupId, fn($q) => $q->where('company_group_id', $groupId))
            ->orderBy('name')->pluck('name', 'id')->all();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Munkáltató (primer)')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('position.name')->label('Pozíció')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Terület')
                    ->getStateUsing(fn(Employee $record) => $record->position)
                    ->toggleable()
                    ->searchable()
                    ->sortable(query: fn(EloquentBuilder $query) => $query->orderBy('position')),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()->toggleable(),

                Tables\Columns\TextColumn::make('shiftPattern.name')
                    ->label('Műszak minta')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),

                /*     Tables\Columns\TextColumn::make('shiftPatternInfo')
                    ->label('Idő / Napok')
                    ->getStateUsing(fn (Employee $record) =>
                        $record->shiftPattern
                            ? "{$record->shiftPattern->start_time}–{$record->shiftPattern->end_time} • {$record->shiftPattern->days_label}"
                            : '—'
                    )
                    ->toggleable(),*/
                Tables\Columns\TextColumn::make('card.uid')
                    ->label('Kártya UID')
                    ->placeholder('— nincs —')
                    ->searchable()
                    ->toggleable(true), //isToggledHiddenByDefault:
            ])
            ->filters([
                TrashedFilter::make(),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Munkáltató (primer)')
                    ->options($groupCompanies),
                Tables\Filters\SelectFilter::make('position_id')
                    ->label('Pozíció')
                    ->options(fn() => Position::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (EloquentBuilder $q, array $data) {
                        if (!empty($data['value'])) {
                            $q->where('position_id', (int) $data['value']);
                        }
                    }),
                Tables\Filters\SelectFilter::make('position')
                    ->label('Terület')
                    ->options(
                        Employee::query()
                            ->distinct()
                            ->orderBy('position')
                            ->pluck('position', 'position')
                            ->filter()
                            ->toArray()
                    ),
                Tables\Filters\SelectFilter::make('member_company')
                    ->label('Cég (tagság)')
                    ->options($groupCompanies)
                    ->query(function (Builder $q, array $data) {
                        if (!empty($data['value'])) {
                            $companyId = (int) $data['value'];
                            $q->whereHas('companies', fn($c) => $c->where('company_id', $companyId));
                        }
                    }),

                Tables\Filters\SelectFilter::make('employment_type')
                    ->label('Foglalkoztatás')
                    ->options([
                        'full_time' => 'Teljes munkaidő',
                        'part_time' => 'Részmunkaidő',
                        'casual'    => 'Alkalmi',
                    ]),

                TernaryFilter::make('present')
                    ->label('Jelenlét')
                    ->placeholder('Mind')
                    ->trueLabel('Bejelentkezve')
                    ->falseLabel('Nincs bejelentkezve')
                    ->queries(
                        true: fn(Builder $q) => $q->whereExists(function ($s) {
                            $s->select(DB::raw(1))
                                ->from('time_entries as te')
                                ->whereColumn('te.employee_id', 'employees.id')
                                ->whereNull('te.end_time')
                                ->whereNull('te.end_date');
                        }),
                        false: fn(Builder $q) => $q->whereNotExists(function ($s) {
                            $s->select(DB::raw(1))
                                ->from('time_entries as te')
                                ->whereColumn('te.employee_id', 'employees.id')
                                ->whereNull('te.end_time')
                                ->whereNull('te.end_date');
                        }),
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('group_company_quick')
                    ->label('Cég szűrés')
                    ->icon('heroicon-o-building-office-2')
                    ->form([
                        Forms\Components\Select::make('company')
                            ->label('Cég')
                            ->options($groupCompanies)
                            ->native(false)
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        $company = $data['company'] ?? null;
                        if ($company) {
                            return redirect()->to(url()->current() . '?tableFilters[company_id][value]=' . $company);
                        }
                    }),

                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('company')
                            ->label('Cég (opcionális)')
                            ->options($groupCompanies)
                            ->native(false)
                            ->searchable(),
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Oszlopok')
                            ->options([
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position' => 'Pozíció',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                            ])
                            ->default(['name', 'company', 'position', 'phone', 'shift'])
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        $cols = $data['columns'] ?? ['name', 'company', 'position', 'phone', 'shift'];
                        $company = $data['company'] ?? null;

                        $q = EmployeeResource::getEloquentQuery()->clone();
                        if ($company) {
                            $q->where('company_id', $company);
                        }
                        $rows = $q->with(['position', 'shiftPattern', 'company'])
                            ->orderBy('name')
                            ->get();

                        $headers = ['Sorszám'];
                        foreach ($cols as $c) {
                            $headers[] = match ($c) {
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position' => 'Pozíció',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                                default => ucfirst($c),
                            };
                        }

                        $html = '<html><head><meta charset="UTF-8"><style>
                            table{width:100%;border-collapse:collapse;font-size:12px}
                            th,td{border:1px solid #ccc;padding:6px;text-align:left}
                            h1{font-size:16px;margin:0 0 10px 0}
                        </style></head><body>';
                        $html .= '<h1>Dolgozók export (PDF)</h1>';
                        $html .= '<table><thead><tr>';
                        foreach ($headers as $h) {
                            $html .= '<th>' . htmlspecialchars($h) . '</th>';
                        }
                        $html .= '</tr></thead><tbody>';

                        $sorszam = 0;
                        foreach ($rows as $r) {
                            $sorszam++;
                            $html .= '<tr>';
                            $html .= '<td>' . $sorszam . '</td>';
                            foreach ($cols as $c) {
                                $val = match ($c) {
                                    'name' => $r->name,
                                    'company' => $r->company?->name,
                                   'position' => is_string($r->position ?? null) ? $r->position : ($r->position?->name ?? ''),
                                    'phone' => $r->phone,
                                    'shift' => $r->shiftPattern?->name,
                                    default => '',
                                };
                                $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
                            }
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table></body></html>';

                        $resp = new StreamedResponse(function () use ($html) {
                            $options = new \Dompdf\Options([
                                'isRemoteEnabled' => true,
                                'defaultFont' => 'DejaVu Sans',
                            ]);
                            $dompdf = new \Dompdf\Dompdf($options);
                            $dompdf->loadHtml($html, 'UTF-8');
                            $dompdf->setPaper('A4', 'portrait');
                            $dompdf->render();
                            echo $dompdf->output();
                        }, 200, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename=\"employees.pdf\"',
                        ]);
                        return $resp;
                    }),

                Tables\Actions\Action::make('export_xls')
                    ->label('Export XLS')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('company')
                            ->label('Cég (opcionális)')
                            ->options($groupCompanies)
                            ->native(false)
                            ->searchable(),
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Oszlopok')
                            ->options([
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position_name' => 'Pozíció', // reláció
                                'area' => 'Terület',          // string mező (employees.position)
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                            ])
                            ->default(['name', 'company', 'position_name', 'phone'])
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        $cols = $data['columns'] ?? ['name', 'company', 'position', 'phone'];
                        $company = $data['company'] ?? null;

                        $q = EmployeeResource::getEloquentQuery()->clone();
                        if ($company) {
                            $q->where('company_id', $company);
                        }
                        $rows = $q->with(['position', 'shiftPattern', 'company'])
                            ->orderBy('name')
                            ->get();

                        $headers = [];
                        foreach ($cols as $c) {
                            $headers[] = match ($c) {
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position_name' => 'Pozíció',
                                'area' => 'Terület',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                                default => ucfirst($c),
                            };
                        }

                        $html = '<table border=\"1\"><thead><tr>';
                        foreach ($headers as $h) {
                            $html .= '<th>' . htmlspecialchars($h) . '</th>';
                        }
                        $html .= '</tr></thead><tbody>';
                        foreach ($rows as $r) {
                                Log::debug('dbg', [
                                    'id' => $r->id ?? null,
                                    'has_attr_position' => is_object($r) ? array_key_exists('position', $r->getAttributes()) : null,
                                    'attr_position' => is_object($r) ? $r->getAttribute('position') : null,
                                    'attr_position_type' => is_object($r) ? gettype($r->getAttribute('position')) : gettype($r),
                                    'relation_loaded' => is_object($r) ? $r->relationLoaded('position') : null,
                                    'relation_type' => (is_object($r) && $r->relationLoaded('position'))
                                        ? get_class($r->getRelation('position'))
                                        : null,
                                ]);
                            $html .= '<tr>';
                            foreach ($cols as $c) {

                                $val = match ($c) {
                                    'name' => $r->name,
                                    'company' => $r->company?->name,
                                    'position_name' => $r->position?->name ?? '',
                                    'area' => $r->getAttribute('position') ?? '', // direkt attribútum: Terület
                                    'phone' => $r->phone,
                                    'shift' => $r->shiftPattern?->name,
                                    default => '',
                                };
                                $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
                            }
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table>';

                        return new StreamedResponse(function () use ($html) {
                            echo "\xEF\xBB\xBF"; // BOM – Excel szereti UTF-8-hoz
                            echo $html;
                        }, 200, [
                            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                            'Content-Disposition' => 'attachment; filename="employees.xls"',
                            'Cache-Control' => 'no-store, no-cache',
                        ]);
                    }),
            ])
            ->actionsPosition(ActionsPosition::AfterColumns)
            ->actions([
                Tables\Actions\Action::make('assignCard')
                    ->label('')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn($record) => ! $record->card) // 1-1 kapcsolat esetén
                    ->color('success')
                    ->tooltip('Kártya hozzárendelése')
                    ->form([
                        Forms\Components\Select::make('card_id')
                            ->label('Szabad kártya')
                            ->options(fn() => Card::available()->orderBy('uid')->pluck('uid', 'id'))
                            ->searchable()
                            ->required()
                            ->options(function () {
                                return Card::query()
                                    ->whereNull('employee_id')
                                    ->where('status', 'available')
                                    ->orderBy('uid')
                                    ->limit(100) // opcionális
                                    ->get()
                                    ->mapWithKeys(function (Card $c) {
                                        $label = trim($c->uid . ($c->notes ? ' - ' . $c->notes : ''));
                                        return [$c->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                return Card::query()
                                    ->whereNull('employee_id')
                                    ->where('status', 'available')
                                    ->where(function ($q) use ($search) {
                                        $q->where('uid', 'like', "%{$search}%")
                                            ->orWhere('notes', 'like', "%{$search}%");
                                    })
                                    ->orderBy('uid')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Card $c) {
                                        $label = trim($c->uid . ($c->notes ? ' - ' . $c->notes : ''));
                                        return [$c->id => $label];
                                    })
                                    ->toArray();
                            })
                            // Ha már kiválasztott értéket kell visszaírni címkének
                            ->getOptionLabelUsing(function ($value) {
                                if (! $value) return null;
                                $c = Card::find($value);
                                return $c ? trim($c->uid . ($c->notes ? ' - ' . $c->notes : '')) : null;
                            }),
                    ])
                    ->action(function ($record, array $data) {
                        $card = Card::findOrFail($data['card_id']);
                        app(CardService::class)->assignByUid($record->id, $card->uid);
                        Notification::make()->title('Kártya hozzárendelve.')->success()->send();
                    })
                    ->after(function (Action $action, $livewire) {
                        $livewire->dispatch('refresh');   // táblát/oldalt újrarendereli
                    }),

                Tables\Actions\Action::make('unassignCard')
                    ->label('')
                    ->icon('heroicon-o-minus-circle')
                    ->visible(fn($record) => (bool) $record->card)
                    ->color('warning')
                    ->tooltip('Kártya léválasztása')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(CardService::class)->unassign($record->card->id);
                        Notification::make()->title('Kártya leválasztva.')->success()->send();
                    })
                    ->after(function (Action $action, $livewire) {
                        $livewire->dispatch('refresh');   // táblát/oldalt újrarendereli
                    }),

                // Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('checkIn')
                    ->label('')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->iconButton()
                    ->size(ActionSize::Large)
                    ->color('success')
                    ->tooltip('Bejelentkezés')
                    ->visible(function (Employee $record) {
                        if (! Schema::hasTable('time_entries')) return false;

                        return ! TimeEntry::where('employee_id', $record->id)
                            ->where('type', TimeEntryType::Presence->value)
                            ->whereNull('end_time')
                            ->whereNull('end_date')
                            ->exists();
                    })
                    ->modalWidth('sm')
                    ->form([
                        Forms\Components\Grid::make(1)->schema([
                            Forms\Components\DatePicker::make('date')->label('Dátum')->default(now())->native(false)->required(),
                            Forms\Components\TimePicker::make('time')->label('Idő')->default(now())->seconds(false)->minutesStep(5)->required(),
                        ]),
                    ])
                    ->action(function (Employee $record, array $data) {
                        if (! Schema::hasTable('time_entries')) {
                            throw new \RuntimeException('Hiányzik a time_entries tábla.');
                        }
                        $date = Carbon::parse($data['date'])->toDateString();
                        $time = Carbon::parse($data['time'])->format('H:i');

                        $uid = Filament::auth()->id() ?? Auth::id();

                        TimeEntry::create([
                            'employee_id'  => $record->id,
                            'company_id'   => $record->company_id,
                            'type'         => TimeEntryType::Presence->value,
                            'start_date'   => $date,
                            'start_time'   => $time,
                            'status'       => TimeEntryStatus::CheckedIn->value,
                            'requested_by' => $uid,
                            'approved_by'  => $uid,
                        ]);
                    })
                    ->successNotificationTitle('Bejelentkezve'),

                Tables\Actions\Action::make('checkOut')
                    ->label('')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->iconButton()
                    ->size(ActionSize::Large)
                    ->color('warning')
                    ->tooltip('Kijelentkezés')
                    ->visible(function (Employee $record) {
                        if (! Schema::hasTable('time_entries')) return false;

                        return TimeEntry::where('employee_id', $record->id)
                            ->where('type', TimeEntryType::Presence->value)
                            ->whereNull('end_time')
                            ->whereNull('end_date')
                            ->exists();
                    })
                    ->modalWidth('sm')
                    ->form([
                        Forms\Components\Grid::make(1)->schema([
                            Forms\Components\DatePicker::make('date')->label('Dátum')->default(now())->native(false)->required(),
                            Forms\Components\TimePicker::make('time')->label('Idő')->default(now())->seconds(false)->minutesStep(5)->required(),
                        ]),
                    ])
                    ->action(function (Employee $record, array $data) {
                        if (! Schema::hasTable('time_entries')) {
                            throw new \RuntimeException('Hiányzik a time_entries tábla.');
                        }

                        $date = Carbon::parse($data['date'])->toDateString();
                        $time = Carbon::parse($data['time'])->format('H:i');

                        $open = TimeEntry::where('employee_id', $record->id)
                            ->where('type', TimeEntryType::Presence->value)
                            ->whereNull('end_time')
                            ->whereNull('end_date')
                            ->orderByDesc('id')
                            ->first();

                        if (! $open) {
                            throw new \RuntimeException('Nincs nyitott jelenlét rögzítve.');
                        }

                        // Az órák és a túlóra-keret módosítása a TimeEntryObserver-ben történik,
                        // itt csak a záró időpontot és az állapotot állítjuk be.
                        $open->end_date = $date;
                        $open->end_time = $time;
                        $open->status = TimeEntryStatus::CheckedOut->value;
                        $open->save();
                    })
                    ->successNotificationTitle('Kijelentkezve'),

                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Archiválás'),
                Tables\Actions\RestoreAction::make()->label('')->tooltip('Visszaállítás'),
                Tables\Actions\ForceDeleteAction::make()
                    ->label('')
                    ->tooltip('Végleges törlés')
                    ->visible(fn($record) => ($record?->trashed() ?? false)
                        && (Filament::auth()->user()?->role ?? null) === 'admin'),
            ])
            ->bulkActions([
                static::attendanceSheetBulkAction(
                    name: 'attendance_sheet',
                    label: 'Jelenléti ív nyomtatása',
                    view: 'exports.attendance-sheet',
                    filenamePrefix: 'jelenleti_iv',
                ),
                static::attendanceSheetBulkAction(
                    name: 'attendance_sheet_detailed',
                    label: 'Részletes jelenléti ív (soronkénti be-/kilépések)',
                    view: 'exports.attendance-sheet-detailed',
                    filenamePrefix: 'jelenleti_iv_reszletes',
                ),

                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Archiválás'),
                    Tables\Actions\RestoreBulkAction::make()->label('Visszaállítás'),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Végleges törlés')
                        ->visible(fn() => (Filament::auth()->user()?->role ?? null) === 'admin'),
                ]),
            ])
            ->defaultSort('name');
    }

    /**
     * Közös építő az "egyszerű" és a "részletes" jelenléti ív tömeges nyomtatásához — csak
     * a nézetsablonban és a fájlnév-előtagban térnek el, minden más (év/hónap-választó,
     * dolgozó-szűrés, PDF-generálás) azonos.
     */
    protected static function attendanceSheetBulkAction(string $name, string $label, string $view, string $filenamePrefix): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make($name)
            ->label($label)
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->form([
                // Korábban nem volt évválasztó, csak now()->year volt hardkódolva, ezért
                // egy korábbi (pl. tavalyi) importált időszak jelenléti íve sosem volt
                // lekérhető erről a felületről.
                Forms\Components\Select::make('year')
                    ->label('Év')
                    ->options(collect(range(now()->year, now()->year - 3))->mapWithKeys(fn ($y) => [$y => $y]))
                    ->default(now()->year)
                    ->required(),
                Forms\Components\CheckboxList::make('months')
                    ->label('Hónap(ok)')
                    ->options([
                        '01' => 'Január',   '02' => 'Február', '03' => 'Március',
                        '04' => 'Április',  '05' => 'Május',   '06' => 'Június',
                        '07' => 'Július',   '08' => 'Augusztus', '09' => 'Szeptember',
                        '10' => 'Október',  '11' => 'November', '12' => 'December',
                    ])
                    ->default([now()->format('m')])
                    ->columns(3)
                    ->required(),
            ])
            ->action(function (\Illuminate\Support\Collection $records, array $data) use ($view, $filenamePrefix) {
                $year = (int) ($data['year'] ?? now()->year);
                $months = collect($data['months'] ?? [])->sort()->values();

                $employees = $records
                    ->reject(fn (Employee $e) => $e->trashed())
                    ->sortBy('name')
                    ->values();

                if ($employees->isEmpty()) {
                    Notification::make()
                        ->title('Nincs aktív dolgozó a kijelölés között.')
                        ->warning()
                        ->send();
                    return;
                }

                $service = app(\App\Services\AttendanceSheetService::class);
                $sheets = [];
                foreach ($employees as $employee) {
                    $employee->loadMissing('company');
                    foreach ($months as $m) {
                        $periodStart = \Carbon\CarbonImmutable::createFromDate($year, (int) $m, 1)->startOfMonth();
                        $periodEnd = $periodStart->endOfMonth();
                        $sheets[] = $service->buildForEmployee($employee, $periodStart, $periodEnd);
                    }
                }

                $html = view($view, [
                    'sheets'    => $sheets,
                    'printedAt' => now()->format('Y-m-d H:i'),
                ])->render();

                $options = new \Dompdf\Options(['defaultFont' => 'DejaVu Sans']);
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                $filenameMonths = $months->implode('_') ?: now()->format('m');

                // "inline", nem "attachment": alapértelmezésben megnyíljon (új böngésző-fülön),
                // ne letöltésre kényszerítse a felhasználót.
                return new StreamedResponse(function () use ($dompdf) {
                    echo $dompdf->output();
                }, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$filenamePrefix.'_'.$year.'_'.$filenameMonths.'.pdf"',
                ]);
            })
            ->deselectRecordsAfterCompletion();
    }
}
