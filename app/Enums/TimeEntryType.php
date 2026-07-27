<?php
namespace App\Enums;

enum TimeEntryType: string
{
    case Regular   = 'regular';
    case Vacation  = 'vacation';   // szabadság
    case Overtime  = 'overtime';   // túlóra
    case SickLeave = 'sick_leave'; // táppénz
    case Presence  = 'presence';
    case UnauthorizedAbsence = 'unauthorized_absence'; // igazolatlan távollét
}
