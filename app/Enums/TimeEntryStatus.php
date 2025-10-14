<?php
namespace App\Enums;

enum TimeEntryStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Open  = 'open';
    case CheckedIn  = 'checked_in';   // bejelentkezve
    case CheckedOut = 'checked_out';  // kijelentkezve
}
