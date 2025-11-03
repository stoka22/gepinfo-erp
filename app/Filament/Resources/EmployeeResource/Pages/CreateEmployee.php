<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user();
        // nem adminnál a tulaj mindig a létrehozó
        if (($user->role ?? null) !== 'admin') {
            $data['user_id'] = $user->id;
        }
        return $data;
    }
}
