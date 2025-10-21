<?php

namespace App\Filament\Pages;

use App\Models\Skill;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Workflow;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CapabilityMatrix extends Page
{
    protected static ?string $navigationIcon   = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup  = 'Dolgozók';
    protected static ?string $navigationLabel  = 'Képességmátrix';
    protected static ?string $title            = 'Képességmátrix (workflow alapú)';
    protected static string  $view             = 'filament.pages.capability-matrix';

    /** A nézetben könnyebb kollekciókkal dolgozni – ne alakítsuk azonnal tömbbé. */
    public $employees;  // Collection<Employee>
    public $skills;     // Collection<Skill>
    public $workflows;  // Collection<Workflow>
    public array $matrix = []; // [employee_id][skill_id] = level

    public function mount()
    {
        $groupIds = $this->companyGroupIds();

        // Dolgozók a cégcsoportból + skillek pivot 'level' mezővel
        $this->employees = Employee::query()
            ->when($groupIds, fn ($q) => $q->whereIn('company_id', $groupIds))
            ->with(['skills' => fn ($q) => $q->select('skills.id', 'skills.name')])
            ->orderBy('name')
            ->get();

        // Skillek / Workfl ow-k – csak akkor szűrjük, ha van company_id oszlopuk
        $this->skills = Skill::query()
            ->when($groupIds && Schema::hasColumn('skills', 'company_id'),
                fn ($q) => $q->whereIn('company_id', $groupIds))
            ->orderBy('name')
            ->get();

        $this->workflows = Workflow::query()
            ->when($groupIds && Schema::hasColumn('workflows', 'company_id'),
                fn ($q) => $q->whereIn('company_id', $groupIds))
            ->with(['skills' => fn ($q) => $q->select('skills.id', 'skills.name')])
            ->orderBy('name')
            ->get();

        // Mátrix feltöltése a már leszűrt employees-ból
        $this->matrix = [];
        foreach ($this->employees as $emp) {
            foreach ($emp->skills as $sk) {
                // feltételezve: many-to-many -> withPivot('level')
                $this->matrix[$emp->id][$sk->id] = (int) ($sk->pivot->level ?? 0);
            }
        }
    }

    /** Cégcsoport ID-k: group_id szerint, vagy parent-fa (egyszintű); fallback: saját cég */
    protected function companyGroupIds(): ?array
    {
        $tenant  = Filament::getTenant();
        $company = $tenant instanceof Company ? $tenant : (Auth::user()?->company ?? null);
        if (! $company) {
            return null;
        }

        if (isset($company->group_id) && $company->group_id) {
            return Company::query()->where('group_id', $company->group_id)->pluck('id')->all();
        }

        if (isset($company->parent_id)) {
            $parentId = $company->parent_id ?: $company->id;
            return Company::query()
                ->where(fn ($q) => $q->where('id', $parentId)->orWhere('parent_id', $parentId))
                ->pluck('id')->all();
        }

        return [$company->id];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $u = Auth::user();
        return $u?->hasRole('admin') || $u?->can('view capability matrix') || $u?->can('manage workflows');
    }
}
