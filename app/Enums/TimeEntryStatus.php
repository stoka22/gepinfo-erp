<?php
namespace App\Enums;

enum TimeEntryStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
