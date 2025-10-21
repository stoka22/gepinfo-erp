<?php
// app/Filament/Widgets/AbsenceTodayTable.php

namespace App\Filament\Widgets;

use App\Enums\TimeEntryType;
use App\Models\Company;
use App\Models\TimeEntry;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class AbsenceTodayTable extends BaseWidget
{
    protected static ?string $heading = 'Távolléten lévő dolgozók (ma)';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $today = Carbon::today()->toDateString();

        return $table
            ->query(fn () => $this->queryAbsences($today))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')->label('Dolgozó')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employee.position.name')->label('Beosztás'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Típus')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $enum = $state instanceof TimeEntryType ? $state : TimeEntryType::tryFrom((string)$state);
                        return match ($enum) {
                            TimeEntryType::Vacation  => 'Szabadság',
                            TimeEntryType::SickLeave => 'Betegszabi',
                            TimeEntryType::Overtime  => 'Túlóra',
                            TimeEntryType::Presence  => 'Jelenlét',
                            TimeEntryType::Regular   => 'Munkaidő',
                            default                  => (string) $state,
                        };
                    }),
                Tables\Columns\TextColumn::make('start_date')->label('Kezdet')->date('Y-m-d'),
                Tables\Columns\TextColumn::make('end_date')->label('Vége')->date('Y-m-d'),
            ])
            ->emptyStateHeading('Ma nincs távollét')
            ->defaultSort('employee.name');
    }

    protected function queryAbsences(string $today): Builder
    {
        $groupIds = $this->companyGroupIds();

        return TimeEntry::query()
            ->with(['employee.position'])
            ->when($groupIds !== null, fn ($q) => $q->whereIn('company_id', $groupIds))
            ->whereIn('type', [TimeEntryType::Vacation->value, TimeEntryType::SickLeave->value])
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
    }

    protected function companyGroupIds(): ?array
    {
        $tenant = Filament::getTenant();
        $company = $tenant instanceof Company ? $tenant : (Filament::auth()->user()->company ?? null);
        if (!$company) return null;

        if (isset($company->group_id)) {
            return Company::query()->where('group_id', $company->group_id)->pluck('id')->all();
        }

        if (isset($company->parent_id)) {
            $parentId = $company->parent_id ?: $company->id;
            $ids = Company::query()
                ->where(fn($q) => $q->where('id', $parentId)->orWhere('parent_id', $parentId))
                ->pluck('id')->all();
            return $ids ?: [$company->id];
        }

        return [$company->id];
    }
}
