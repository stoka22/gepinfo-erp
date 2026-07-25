<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeEntryResource\Pages;
use App\Models\TimeEntry;
use App\Models\Company;
use App\Models\Employee;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use App\Filament\Resources\TimeEntryResource\Forms\TimeEntryForm;
use App\Filament\Resources\TimeEntryResource\Tables\TimeEntryTable;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Szabadság / Túlóra / Táppénz';

   public static function accessibleCompanyIds(): array
    {
        
      /*  logger()->info('accessibleCompanyIds debug', [
            'user_id' => Auth::id(),
            'can_manage_group' => Auth::user()?->can('manage group time entries'),
            'user_company_id' => Auth::user()?->company_id,
            'tenant_id' => \Filament\Facades\Filament::getTenant()?->id,
            'tenant_company_group_id' => \Filament\Facades\Filament::getTenant()?->company_group_id,
        ]);  */
    /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenant = Filament::getTenant();

        $company = $tenant instanceof Company
            ? $tenant
            : ($user?->company ?? null);

        if (! $company) {
            return [];
        }

        // FONTOS: a valós permission nevet ellenőrizzük
        if (! $user?->can('manage group time entries')) {
            return [$company->id];
        }

        // ha van company group
        if (! empty($company->company_group_id)) {
            return Company::query()
                ->where('company_group_id', $company->company_group_id)
                ->pluck('id')
                ->all();
        }

        // opcionális fallback
        if (isset($company->parent_id) && $company->parent_id !== null) {
            $parentId = $company->parent_id ?: $company->id;

            return Company::query()
                ->where(function ($q) use ($parentId) {
                    $q->where('id', $parentId)
                    ->orWhere('parent_id', $parentId);
                })
                ->pluck('id')
                ->all();
        }

        return [$company->id];
    }

    public static function accessibleCompaniesForFilter(): array
    {
        return Company::query()
            ->whereIn('id', static::accessibleCompanyIds())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function companyGroupIds(): ?array
    {
        $tenant  = Filament::getTenant();
        $company = $tenant instanceof Company ? $tenant : (Auth::user()?->company ?? null);
        if (! $company) {
            return null;
        }

        // 1) group_id szerinti csoport
        if (isset($company->group_id) && $company->group_id) {
            return Company::query()
                ->where('group_id', $company->group_id)
                ->pluck('id')
                ->all();
        }

        // 2) parent_id fa (egyszintű)
        if (isset($company->parent_id)) {
            $parentId = $company->parent_id ?: $company->id;
            return Company::query()
                ->where(function ($q) use ($parentId) {
                    $q->where('id', $parentId)
                      ->orWhere('parent_id', $parentId);
                })
                ->pluck('id')
                ->all();
        }

        // 3) csak a saját cég
        return [$company->id];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return TimeEntryForm::configure($form);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return TimeEntryTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('company_id', static::accessibleCompanyIds());
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTimeEntries::route('/'),
            'create' => Pages\CreateTimeEntry::route('/create'),
            'edit'   => Pages\EditTimeEntry::route('/{record}/edit'),
        ];
    }

    protected static function employeeOptionsQuery(?string $search = null): Builder
    {
        $groupIds = static::companyGroupIds();

        return Employee::query()
            ->when(!empty($groupIds), fn ($q) => $q->whereIn('company_id', $groupIds))
            ->when(
                filled($search),
                fn ($q) => $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
            );
    }
}
