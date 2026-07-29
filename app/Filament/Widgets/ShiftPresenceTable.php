<?php
// app/Filament/Widgets/ShiftPresenceTable.php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Forms;
use App\Enums\Shift;
use Filament\Tables;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use Filament\Tables\Table;
use App\Enums\TimeEntryType;
use App\Models\ShiftPattern;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\TableWidget as BaseWidget;

class ShiftPresenceTable extends BaseWidget
{
    protected static ?string $heading = 'Műszak szerinti jelenlét (ma)';
    protected static ?int $sort = -10;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $today = Carbon::today();

        return $table
            ->query(fn () => $this->baseQueryForToday(Carbon::today()))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Dolgozó')
                    ->searchable()
                    ->sortable()
                    ->color(fn (Employee $record) => $this->isCheckedInToday($record->id, $today) ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Cég')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                // Műszak neve + tooltip a mai ablakról
                Tables\Columns\TextColumn::make('shift_display')
                    ->label('Műszak')
                    ->state(fn(Employee $r) =>
                        $r->shiftPattern?->name ?? match($r->shift) {
                            'morning' => 'Délelőtt',
                            'afternoon' => 'Délután',
                            'night' => 'Éjszaka',
                            default => '—',
                        }
                    )
                    ->tooltip(fn (Employee $record) => $this->getShiftTooltipToday($record, $today))
                    ->sortable(query: fn (\Illuminate\Database\Eloquent\Builder $q, string $direction) =>
                        $q->orderBy('shift', $direction) // <-- VALÓDI MEZŐ
                    ),
                Tables\Columns\BadgeColumn::make('mode')
                    ->label('Jelleg')
                    ->state(function (Employee $record) use ($today) {
                        $e = $this->firstPresenceEntryToday($record->id, $today);
                        return $e?->entry_method === 'card' ? 'Kártya' : ($e ? 'Irodai' : null);
                    })
                    ->color(fn ($state) => $state === 'Kártya' ? 'success' : 'warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('first_check_in')
                    ->label('Bejelentkezés')
                    ->state(fn (Employee $record) => $this->firstCheckInToday($record->id, $today))
                    ->color(function (Employee $record) use ($today) {
                        $e = $this->firstPresenceEntryToday($record->id, $today);
                        return ($e && ($e->entry_method === 'office' || $e->is_modified)) ? 'danger' : null;
                    }),

                Tables\Columns\TextColumn::make('last_check_out')
                    ->label('Kijelentkezés')
                    ->state(fn (Employee $record) => $this->lastCheckOutToday($record->id, $today)),
                Tables\Columns\TextColumn::make('worked_today')
                    ->label('Eltöltött idő')
                    ->state(fn (Employee $record) => $this->workedHoursTodayFormatted($record->id, $today)), // pl. 07:35
            ])
            ->actions([
                Tables\Actions\Action::make('check_in')
                    ->label('')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->iconButton()
                    ->color('success') 
                    ->extraAttributes(['class' => 'text-4xl'])
                    ->tooltip('Bejelentkezés (induló idő rögzítése)')
                    ->requiresConfirmation()
                    ->visible(fn (Employee $record) => !$this->hasOpenPresenceToday($record->id, $today))
                    ->form([
                            TimePicker::make('time')
                                ->label('Bejelentkezés ideje')
                                ->seconds(false)
                                ->minutesStep(5)
                                ->default(now())          // a mai időt dobja fel
                                ->native(false)
                                ->required(),
                            Select::make('entry_method')
                                ->label('Jelleg')
                                ->options(['card' => 'Kártya', 'office' => 'Irodai'])
                                ->default('office')
                                ->required(),
                        ])
                    ->action(function (Employee $record, array $data) use ($today) {
                        $time = $this->onlyTime($data['time']); // 'HH:MM:SS'
                        $entry = TimeEntry::create([
                            'employee_id'  => $record->id,
                            'company_id'   => $record->company_id ?? null,
                            'type'         => \App\Enums\TimeEntryType::Presence->value,
                            'status'       => \App\Enums\TimeEntryStatus::CheckedIn->value,
                            'start_date'   => $today->toDateString(),
                            'start_time'   => $time,
                            'entry_method' =>  'office',
                            'is_modified'  => true,
                            'requested_by' => \Filament\Facades\Filament::auth()->id(),
                            'approved_by'  => \Filament\Facades\Filament::auth()->id(),
                        ]);
                        if (empty($entry->start_time)) {
                            $entry->forceFill(['start_time' => $time])->save();
                        }
                    }),

               Tables\Actions\Action::make('check_out')
                    ->label('')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->iconButton()
                    ->color('danger')
                    ->extraAttributes(['class' => 'text-4xl'])
                    ->tooltip('Kijelentkezés')
                    ->visible(fn (Employee $record) => $this->hasOpenPresenceToday($record->id, $today))
                    ->form([
                        Forms\Components\TimePicker::make('time')
                            ->label('Kijelentkezés ideje')
                            ->seconds(false)->minutesStep(5)->default(now())->native(false)->required(),
                        Forms\Components\Select::make('entry_method')
                            ->label('Jelleg')
                            ->options(['card' => 'Kártya', 'office' => 'Irodai'])
                            ->default('office')->required(),
                    ])
                    ->action(function (\App\Models\Employee $record, array $data) use ($today) {
    DB::transaction(function () use ($record, $data, $today) {
        $open = \App\Models\TimeEntry::query()
            ->where('employee_id', $record->id)
            ->where('type', \App\Enums\TimeEntryType::Presence->value)
            ->whereDate('start_date', $today)
            ->whereNull('end_time')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $open) {
            return;
        }

        // Az órák és a túlóra-keret módosítása a TimeEntryObserver-ben történik
        // (8 óra 30 perces napi küszöb, göngyölt keret).
        $open->update([
            'end_date'     => $today->toDateString(),
            'end_time'     => $this->onlyTime($data['time']),
            'status'       => \App\Enums\TimeEntryStatus::CheckedOut->value,
            'entry_method' => $data['entry_method'] ?? $open->entry_method,
            'approved_by'  => $open->approved_by ?? \Filament\Facades\Filament::auth()->id(),
            'is_modified'  => ($data['entry_method'] ?? $open->entry_method) === 'office',
            'modified_by'  => \Filament\Facades\Filament::auth()->id(),
        ]);
    });
})


        ])
            ->filters([
                    // Cég szűrő
                    Tables\Filters\SelectFilter::make('company_id')
                        ->label('Cég')
                        ->relationship('company', 'name'),

                    // Műszak szűrő (enum)
                    Tables\Filters\SelectFilter::make('shift')
                        ->label('Műszak')
                        ->options(fn () => ShiftPattern::query()->orderBy('name')->pluck('name','id')->all())
                        ->placeholder('Mind')
                        ->searchable()
                        ->query(function (Builder $query, array $data): Builder {
                        $id = $data['value'] ?? null;
                        if (! $id) {
                            return $query; // nincs kiválasztás → ne szűrjünk
                        }

                        // kiválasztott minta neve
                        $name = ShiftPattern::whereKey($id)->value('name');

                        // név → régi kód (ha néhány rekord még az employees.shift mezőt használja)
                        $map = [
                            'Délelőtt' => 'morning',
                            'Délután'  => 'afternoon',
                            'Éjszaka'  => 'night',
                        ];
                        $code = $map[$name] ?? null;

                        return $query->where(function (Builder $w) use ($id, $code) {
                            $w->where('employees.shift_pattern_id', $id);
                            if ($code) {
                                $w->orWhere('employees.shift', $code);
                            }
                        });
                    }),

                    // Státusz: bejelentkezett / nincs bejelentkezve ma
                    Tables\Filters\TernaryFilter::make('checked_in')
                        ->label('Ma bejelentkezett')
                        ->placeholder('Mind')
                        ->trueLabel('Csak bejelentkezett')
                        ->falseLabel('Csak nem bejelentkezett')
                        ->queries(
                            true: fn (Builder $q) => $q->whereExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('time_entries as te')
                                    ->whereColumn('te.employee_id', 'employees.id')
                                    ->where('te.type', \App\Enums\TimeEntryType::Presence->value)
                                    ->whereDate('te.start_date', Carbon::today());
                            }),
                            false: fn (Builder $q) => $q->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('time_entries as te')
                                    ->whereColumn('te.employee_id', 'employees.id')
                                    ->where('te.type', \App\Enums\TimeEntryType::Presence->value)
                                    ->whereDate('te.start_date', Carbon::today());
                            }),
                            blank: fn (Builder $q) => $q
                        ),
                    Tables\Filters\TernaryFilter::make('has_overtime')
                        ->label('Van túlóra ma')
                        // Napi több be-/kilépés esetén a szakaszok overtime_delta_minutes értéke
                        // egyenként nem feltétlenül pozitív (ld. TimeEntryObserver napi elszámolás),
                        // csak az ÖSSZEGÜK adja a helyes napi túlórát – ezért SUM > 0, nem EXISTS.
                        ->queries(
                            true: fn (Builder $q) => $q->whereRaw(
                                '(select coalesce(sum(te2.overtime_delta_minutes), 0)
                                    from time_entries te2
                                    where te2.employee_id = employees.id
                                      and te2.type = ?
                                      and te2.start_date = ?) > 0',
                                [TimeEntryType::Presence->value, Carbon::today()->toDateString()]
                            ),
                            false: fn (Builder $q) => $q->whereRaw(
                                '(select coalesce(sum(te2.overtime_delta_minutes), 0)
                                    from time_entries te2
                                    where te2.employee_id = employees.id
                                      and te2.type = ?
                                      and te2.start_date = ?) <= 0',
                                [TimeEntryType::Presence->value, Carbon::today()->toDateString()]
                            ),
                            blank: fn (Builder $q) => $q
                        ),
    
                ])
            ->defaultSort('name');
    }

    

    protected function firstPresenceEntryToday(int $employeeId, \Carbon\Carbon $day): ?TimeEntry
    {
        return TimeEntry::where('employee_id', $employeeId)
            ->where('type', \App\Enums\TimeEntryType::Presence->value)
            ->whereDate('start_date', $day)
            ->orderBy('id', 'asc')
            ->first();
    }


    /** Dolgozók, akik MA nincsenek távolléten, ÉS a cégcsoport tagjai */
    protected function baseQueryForToday(Carbon $today): Builder
    {
        $d = $today->toDateString();
        $groupIds = $this->companyGroupIds();

        return Employee::query()
            ->with(['company'])
            ->when($groupIds !== null, fn ($q) => $q->whereIn('company_id', $groupIds))
            ->whereDoesntHave('timeEntries', function ($q) use ($d) {
                $q->whereIn('type', [TimeEntryType::Vacation->value, TimeEntryType::SickLeave->value, TimeEntryType::UnauthorizedAbsence->value])
                  ->whereDate('start_date', '<=', $d)
                  ->where(function ($qq) use ($d) {
                      $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
                  });
            });
    }

    /** Cégcsoport azonosítók: jelenlegi tenant/user cégének csoportja */
    protected function companyGroupIds(): ?array
    {
        $tenant = Filament::getTenant();
        $company = $tenant instanceof Company ? $tenant : (Filament::auth()->user()->company ?? null);
        if (!$company) return null;

        // 1) Ha van group_id mező → ugyanahhoz a group_id-hoz tartozó összes cég
        if (isset($company->group_id)) {
            return Company::query()->where('group_id', $company->group_id)->pluck('id')->all();
        }

        // 2) Ha fa-struktúra van (parent_id) → a parent + gyermekek (egyszintű fallback)
        if (isset($company->parent_id)) {
            $parentId = $company->parent_id ?: $company->id;
            $ids = Company::query()
                ->where(function ($q) use ($parentId) {
                    $q->where('id', $parentId)->orWhere('parent_id', $parentId);
                })->pluck('id')->all();
            return $ids ?: [$company->id];
        }

        // 3) Fallback: csak a saját cég
        return [$company->id];
    }

    protected function hasOpenPresenceToday(int $employeeId, Carbon $today): bool
    {
        return TimeEntry::where('employee_id', $employeeId)
            ->where('type', TimeEntryType::Presence->value)
            ->whereDate('start_date', $today)
            ->whereNull('end_time')
            ->exists();
    }

    protected function isCheckedInToday(int $employeeId, Carbon $today): bool
    {
        return TimeEntry::where('employee_id', $employeeId)
            ->where('type', TimeEntryType::Presence->value)
            ->whereDate('start_date', $today)
            ->exists();
    }

    protected function firstCheckInToday(int $employeeId, Carbon $today): ?string
    {
        $t = TimeEntry::where('employee_id', $employeeId)
            ->where('type', TimeEntryType::Presence->value)
            ->whereDate('start_date', $today)
            ->min('start_time');

        return $t ? substr($t, 0, 5) : null;
    }

    protected function lastCheckOutToday(int $employeeId, Carbon $today): ?string
    {
        $t = TimeEntry::where('employee_id', $employeeId)
            ->where('type', TimeEntryType::Presence->value)
            ->whereDate('start_date', $today)
            ->whereNotNull('end_time')
            ->max('end_time');

        return $t ? substr($t, 0, 5) : null;
    }

    /** Műszak név (többféle sémát támogat) */
   

    protected function getShiftName($employee): ?string
    {
        // 1) Ha van shiftPattern neve
        if (isset($employee->shiftPattern?->name) && $employee->shiftPattern->name !== '') {
            return $employee->shiftPattern->name;
        }

        // 2) Ha van shift_name szöveg
        if (isset($employee->shift_name) && $employee->shift_name !== '') {
            return (string) $employee->shift_name;
        }

        // 3) Enum vagy string -> normalizáljuk enumra és feliratra
        $enum = null;

        if ($employee->shift instanceof Shift) {
            $enum = $employee->shift;
        } elseif (isset($employee->shift) && $employee->shift !== '') {
            $enum = Shift::tryFrom((string) $employee->shift);
        }

        if ($enum instanceof Shift) {
            return match ($enum) {
                Shift::Morning   => 'Délelőtt',
                Shift::Afternoon => 'Délután',
                Shift::Night     => 'Éjszaka',
            };
        }

        return null;
    }


    /** Tooltip a MAI ablakról (kezdés–vég) több sémára felkészítve */
   protected function getShiftTooltipToday($employee, Carbon $today): ?string
    {
        // 1) shiftPattern időablak
        if (isset($employee->shiftPattern?->start_time, $employee->shiftPattern?->end_time)
            && $employee->shiftPattern->start_time && $employee->shiftPattern->end_time) {
            return $this->formatWindowForToday($employee->shiftPattern->start_time, $employee->shiftPattern->end_time, $today);
        }

        // 2) egyedi employee start/end time
        if (isset($employee->start_time, $employee->end_time) && $employee->start_time && $employee->end_time) {
            return $this->formatWindowForToday($employee->start_time, $employee->end_time, $today);
        }

        // 3) enum alapú default idők
        $enum = null;
        if ($employee->shift instanceof \App\Enums\Shift) {
            $enum = $employee->shift;
        } elseif (isset($employee->shift) && $employee->shift !== '') {
            $enum = \App\Enums\Shift::tryFrom((string) $employee->shift);
        }

        if ($enum instanceof \App\Enums\Shift) {
            [$s, $e] = match ($enum) {
                \App\Enums\Shift::Morning   => ['06:00:00', '14:00:00'],
                \App\Enums\Shift::Afternoon => ['14:00:00', '22:00:00'],
                \App\Enums\Shift::Night     => ['22:00:00', '06:00:00'], // éjjeles átlóg
            };
            return $this->formatWindowForToday($s, $e, $today);
        }

        return null;
    }


    /** Formázás: ha átlóg éjfélbe, mutassuk a dátumokat is */
    protected function formatWindowForToday(string $startTime, string $endTime, Carbon $today): string
    {
        $start = Carbon::parse($today->toDateString().' '.$startTime);
        $end   = Carbon::parse($today->toDateString().' '.$endTime);

        // éjszakás: ha end <= start, akkor +1 nap
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $sameDay = $start->isSameDay($end);
        if ($sameDay) {
            return $start->format('H:i').' – '.$end->format('H:i').' (ma)';
        }
        return $start->format('Y-m-d H:i').' → '.$end->format('Y-m-d H:i');
    }

    protected function onlyTime($value): string
{
    if ($value instanceof \Carbon\CarbonInterface) return $value->format('H:i:s');
    $s = (string) $value;
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $s)) return strlen($s) === 5 ? $s.':00' : $s;
    return now()->format('H:i:s');
}

// Napi összes perc (lezárt presence sorok összege)
protected function workedMinutesToday(int $employeeId, \Carbon\Carbon $day): int
{
    // end_date lehet NULL -> ilyenkor start_date-et használjuk
    return (int) \App\Models\TimeEntry::query()
        ->where('employee_id', $employeeId)
        ->where('type', \App\Enums\TimeEntryType::Presence->value)
        ->whereDate('start_date', $day)
        ->whereNotNull('end_time')
        ->selectRaw("
            COALESCE(
              SUM(
                TIMESTAMPDIFF(
                  MINUTE,
                  CONCAT(start_date,' ',start_time),
                  CONCAT(COALESCE(end_date,start_date),' ',end_time)
                )
              ),
            0) AS m
        ")
        ->value('m');
}


protected function workedHoursTodayFormatted(int $employeeId, Carbon $day): ?string
{
    $mins = $this->workedMinutesToday($employeeId, $day);
    if ($mins <= 0) return null;
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return sprintf('%02d:%02d', $h, $m);
}

// Mai legkorábbi kezdés
protected function firstPresenceStartToday(int $employeeId, Carbon $day): ?Carbon
{
    $row = TimeEntry::where('employee_id', $employeeId)
        ->where('type', TimeEntryType::Presence->value)
        ->whereDate('start_date', $day)
        ->orderBy('start_time', 'asc')
        ->first(['start_date','start_time']);
    return ($row && $row->start_time)
    ? \Carbon\Carbon::parse($this->dateString($row->start_date).' '.$row->start_time)
    : null;
}

// Mai legkésőbbi vég
protected function lastPresenceEndToday(int $employeeId, Carbon $day): ?Carbon
{
    $row = TimeEntry::where('employee_id', $employeeId)
        ->where('type', TimeEntryType::Presence->value)
        ->whereDate('start_date', $day)
        ->whereNotNull('end_time')
        ->orderBy('end_time', 'desc')
        ->first(['end_date','end_time']);
    return ($row && $row->end_time)
    ? \Carbon\Carbon::parse($this->dateString($row->end_date).' '.$row->end_time)
    : null;
}

protected function dateString(mixed $d): string
{
    return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d;
}

}
