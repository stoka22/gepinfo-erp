<?php

namespace App\Filament\Widgets;

use App\Enums\TimeEntryType;
use App\Filament\Pages\CapacityAnalysis;
use App\Filament\Pages\DeviceFleetHealth;
use App\Filament\Resources\ItemResource;
use App\Filament\Resources\TimeEntryResource;
use App\Models\Company;
use App\Models\TimeEntry;
use App\Services\OperationalAlerts;
use App\Services\Scheduling\CapacityAnalysisService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Vezérlőpult KPI-csík: a legfontosabb, egy pillantásra átlátható számok, cselekvést
 * igénylő tételek elöl (felülvizsgálandó, alacsony készlet, offline eszköz, csúszásveszély),
 * a jelenlét/távollét pedig a lenti részletes táblázatokkal (ShiftPresenceTable,
 * AbsenceTodayTable) összhangban lévő számokat mutat.
 */
class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $alerts = app(OperationalAlerts::class);
        $today = Carbon::today();
        $groupIds = $this->companyGroupIds();

        $presentNow = TimeEntry::query()
            ->when($groupIds !== null, fn ($q) => $q->whereIn('company_id', $groupIds))
            ->where('type', TimeEntryType::Presence->value)
            ->whereDate('start_date', $today)
            ->whereNull('end_time')
            ->distinct('employee_id')
            ->count('employee_id');

        $absentToday = TimeEntry::query()
            ->when($groupIds !== null, fn ($q) => $q->whereIn('company_id', $groupIds))
            ->whereIn('type', [
                TimeEntryType::Vacation->value,
                TimeEntryType::SickLeave->value,
                TimeEntryType::UnauthorizedAbsence->value,
            ])
            ->whereDate('start_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->distinct('employee_id')
            ->count('employee_id');

        $needsReview = $alerts->needsReviewCount();
        $lowStock = $alerts->lowStockItems()->count();
        $offlineDevices = $alerts->offlineDevicesCount();

        // A kapacitás-elemzés recepthez kötött; ha egy tételhez nincs recept felvéve,
        // ne dőljön el emiatt a teljes vezérlőpult, csak maradjon "—" ez a csempe.
        $atRisk = null;
        try {
            $atRisk = app(CapacityAnalysisService::class)
                ->atRiskOrderItems()
                ->where('at_risk', true)
                ->count();
        } catch (\Throwable) {
            // szándékosan elnyelve, lásd fent
        }

        return [
            Stat::make('Jelenlévő most', (string) $presentNow)
                ->description('Bejelentkezve, még nincs kiléptetve')
                ->descriptionIcon('heroicon-o-user-circle')
                ->color('success'),

            Stat::make('Távollévő ma', (string) $absentToday)
                ->description('Szabadság / táppénz / igazolatlan')
                ->descriptionIcon('heroicon-o-user-minus')
                ->color($absentToday > 0 ? 'warning' : 'success'),

            Stat::make('Felülvizsgálandó', (string) $needsReview)
                ->description('Automatikus kiléptetés / hiányos adat')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($needsReview > 0 ? 'danger' : 'success')
                ->url(TimeEntryResource::getUrl('index')),

            Stat::make('Alacsony készlet', (string) $lowStock)
                ->description('Tétel a rendelési szint alatt')
                ->descriptionIcon('heroicon-o-archive-box')
                ->color($lowStock > 0 ? 'warning' : 'success')
                ->url(ItemResource::getUrl('index')),

            Stat::make('Offline eszköz', (string) $offlineDevices)
                ->description('Nem jelentkezett be időben')
                ->descriptionIcon('heroicon-o-signal-slash')
                ->color($offlineDevices > 0 ? 'danger' : 'success')
                ->url(DeviceFleetHealth::getUrl()),

            Stat::make('Csúszásveszélyes rendelés', $atRisk === null ? '—' : (string) $atRisk)
                ->description($atRisk === null ? 'Nincs elég recept-adat' : 'Recept alapján nem tartható határidő')
                ->descriptionIcon('heroicon-o-clock')
                ->color($atRisk ? 'danger' : 'success')
                ->url(CapacityAnalysis::getUrl()),
        ];
    }

    /** Cégcsoport azonosítók: ugyanaz a logika, mint a ShiftPresenceTable/AbsenceTodayTable widgeteknél,
     * hogy a KPI-csempe és a lenti részletes táblák száma mindig összhangban legyen. */
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
                ->where(fn ($q) => $q->where('id', $parentId)->orWhere('parent_id', $parentId))
                ->pluck('id')->all();
            return $ids ?: [$company->id];
        }

        return [$company->id];
    }
}
