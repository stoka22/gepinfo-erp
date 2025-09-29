<?php
// app/Enums/VacationAllowanceType.php
namespace App\Enums;

enum VacationAllowanceType: string
{
    case Child        = 'child';         // gyermek(ek) után
    case Disability   = 'disability';    // megváltozott munkaképesség
    case Under18      = 'under18';       // 18 év alatt
    case SingleParent = 'single_parent'; // egyedülálló szülő (ha használjátok)
    case Other        = 'other';         // egyéb kézi korrekció
}
