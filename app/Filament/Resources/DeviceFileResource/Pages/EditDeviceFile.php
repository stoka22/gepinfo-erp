<?php

namespace App\Filament\Resources\DeviceFileResource\Pages;

use App\Filament\Resources\DeviceFileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceFile extends EditRecord
{
    protected static string $resource = DeviceFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function afterCreate(): void
    {
        $r = $this->record;
        if ($r->file_path && \Storage::exists($r->file_path)) {
            $r->file_size = \Storage::size($r->file_path);
            $r->mime_type = \Storage::mimeType($r->file_path);
            $r->save();
        }
    }
}
