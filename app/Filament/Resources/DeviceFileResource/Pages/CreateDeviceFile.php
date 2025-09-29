<?php

namespace App\Filament\Resources\DeviceFileResource\Pages;

use App\Filament\Resources\DeviceFileResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDeviceFile extends CreateRecord
{
    protected static string $resource = DeviceFileResource::class;
    
    protected function afterCreate(): void
    {
        $r = $this->record;
        if ($r->file_path && Storage::disk('public')->exists($r->file_path)) {
            $r->file_size = Storage::disk('public')->size($r->file_path);
           // $r->mime_type = Storage::disk('public')->mimeType($r->file_path);
            $r->mime_type = mime_content_type(Storage::disk('public')->path($r->file_path));
            $r->save();
        }
    }
}
