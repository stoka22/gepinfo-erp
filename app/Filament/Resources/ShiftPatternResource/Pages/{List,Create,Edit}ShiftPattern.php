<?php

namespace App\Filament\Resources\ShiftPatternResource\Pages;

use App\Filament\Resources\ShiftPatternResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

class ListShiftPatterns extends ListRecords { protected static string $resource = ShiftPatternResource::class; }
class CreateShiftPattern extends CreateRecord { protected static string $resource = ShiftPatternResource::class; }
class EditShiftPattern extends EditRecord { protected static string $resource = ShiftPatternResource::class; }
