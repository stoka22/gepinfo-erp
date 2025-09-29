<?php

namespace App\Filament\Resources\DeviceFileResource\Pages;

use App\Filament\Resources\DeviceFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewDeviceFile extends ViewRecord
{
    protected static string $resource = DeviceFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('download')
                ->label('Letöltés')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => filled($this->record?->file_path))
                ->url(fn () => url(Storage::url($this->record->file_path)))
                ->openUrlInNewTab(),
        ];
    }
}
