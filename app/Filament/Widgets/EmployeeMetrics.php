<?php

// app/Filament/Widgets/EmployeeMetrics.php
namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class EmployeeMetrics extends Widget
{
    protected static string $view = 'filament.widgets.employee-metrics';

    public float $quotaYear = 0.0;
    public float $usedYear  = 0.0;
    public float $available = 0.0;
    public float $overtimeYear = 0.0;
    public float $overtimeMonth = 0.0;

    public function mount()
    {
        // TODO: töltsd ki a valós számításokkal
        $this->quotaYear     = 0.0;
        $this->usedYear      = 0.0;
        $this->available     = 0.0;
        $this->overtimeYear  = 0.0;
        $this->overtimeMonth = 0.0;
    }
}
