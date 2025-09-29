<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditFirmware extends EditRecord
{
    protected static string $resource = FirmwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $fw = $this->record;

        if (! $fw->file_path) {
            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($fw->file_path)) {
            return;
        }

        $fullPath       = $disk->path($fw->file_path);
        $fw->file_size  = $disk->size($fw->file_path);
        //$fw->mime_type  = $disk->mimeType($fw->file_path);
        $fw->mime_type = mime_content_type(Storage::disk('public')->path($fw->file_path));
        $fw->sha256     = @hash_file('sha256', $fullPath) ?: null;

        // saveQuietly: ne indítsunk újabb afterSave ciklust
        $fw->saveQuietly();
    }
}
