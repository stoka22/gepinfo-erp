<?php

namespace App\Filament\Resources\TaskDependencyResource\Pages;

use App\Filament\Resources\TaskDependencyResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListTaskDependencies extends ListRecords
{
    protected static string $resource = TaskDependencyResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Új függőség'),
        ];
    }
}
