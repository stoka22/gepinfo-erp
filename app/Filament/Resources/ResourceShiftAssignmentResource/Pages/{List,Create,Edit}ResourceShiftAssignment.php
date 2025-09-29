<?php

namespace App\Filament\Resources\ResourceShiftAssignmentResource\Pages;

use App\Filament\Resources\ResourceShiftAssignmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

class ListResourceShiftAssignments extends ListRecords { protected static string $resource = ResourceShiftAssignmentResource::class; }
class CreateResourceShiftAssignment extends CreateRecord { protected static string $resource = ResourceShiftAssignmentResource::class; }
class EditResourceShiftAssignment extends EditRecord { protected static string $resource = ResourceShiftAssignmentResource::class; }
