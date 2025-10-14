<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case CheckedIn  = 'checked_in';   // bejelentkezve
    case CheckedOut = 'checked_out';  // kijelentkezve
}
