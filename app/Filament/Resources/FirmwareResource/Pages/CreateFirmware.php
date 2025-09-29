<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateFirmware extends CreateRecord
{
    protected static string $resource = FirmwareResource::class;

    protected function afterCreate(): void
    {
        $fw = $this->record;
        if ($fw->file_path && \Storage::exists($fw->file_path)) {
            $fw->file_size = \Storage::size($fw->file_path);
            $fw->mime_type = \Storage::mimeType($fw->file_path);
            $fw->sha256    = hash('sha256', \Storage::get($fw->file_path));
            $fw->save();
        }
    }
}
