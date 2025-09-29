<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Employee;
use App\Models\Skill;
use App\Models\Workflow;

class CapabilityMatrix extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Képességmátrix';
    protected static ?string $title = 'Képességmátrix (workflow alapú)';
    protected static string $view = 'filament.admin.pages.capability-matrix';

    public ?array $employees = [];
    public ?array $skills = [];
    public ?array $workflows = [];
    public array $matrix = []; // [employee_id][skill_id] = level

    public function mount()
    {
        $this->employees = Employee::with('skills')->orderBy('name')->get()->toArray();
        $this->skills    = Skill::orderBy('name')->get()->toArray();
        $this->workflows = Workflow::with('skills')->orderBy('name')->get()->toArray();

        // töltsük a mátrixot
        $this->matrix = [];
        foreach (Employee::with('skills')->get() as $emp) {
            foreach ($emp->skills as $sk) {
                $this->matrix[$emp->id][$sk->id] = (int) $sk->pivot->level;
            }
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        $u = auth()->user();
        return $u?->hasRole('admin') || $u?->can('view capability matrix') || $u?->can('manage workflows');
    }
}
