<?php

namespace App\Enums;

enum Shift: string
{
    case Morning   = 'morning';   // Délelőtt
    case Afternoon = 'afternoon'; // Délután
    case Night     = 'night';     // Éjszaka
}
