<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewFirmware extends ViewRecord
{
    protected static string $resource = FirmwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('download')
                ->label('Letöltés')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => filled($this->record?->file_path))
                // A fájl a privát 'local' diskről jön (nincs nyilvános
                // URL-je), ezért egy admin-hitelesített stream-letöltés.
                ->action(fn () => Storage::disk('local')
                    ->download($this->record->file_path, $this->record->version.'.bin')),
        ];
    }
}
