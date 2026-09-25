<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Crypt;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Alapból a Mentés a szerkesztő oldalon MARAD (getRedirectUrl() alap
    // értéke null), a Mégse pedig document.referrer-re/böngésző-history
    // back()-re támaszkodik -- SPA (wire:navigate) módban ez nem mindig
    // megbízható. A felhasználó kérésére mindkettő explicit a lista
    // nézetre irányít.
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->color('gray')
            ->url(static::getResource()::getUrl('index'));
    }

    /**
     * A "wifi_networks_input" repeater nem valódi Device-oszlop -- itt
     * fésüljük össze a devices.meta.wifi_networks JSON-nal, titkosítva a
     * jelszavakat. Üresen hagyott jelszó egy MÁR LÉTEZŐ SSID-nél megtartja
     * a korábbi titkosított értéket (SSID szerint párosítva, nem sorrend
     * szerint, mert a lista átrendezhető/bővíthető).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('wifi_networks_input', $data)) {
            return $data;
        }

        $existingByeSsid = collect($this->record->meta['wifi_networks'] ?? [])->keyBy('ssid');

        $networks = collect($data['wifi_networks_input'] ?? [])
            ->map(function (array $row) use ($existingByeSsid) {
                $ssid = trim((string) ($row['ssid'] ?? ''));
                if ($ssid === '') {
                    return null;
                }

                $password = (string) ($row['password'] ?? '');
                $encrypted = $password !== ''
                    ? Crypt::encryptString($password)
                    : ($existingByeSsid->get($ssid)['password'] ?? null);

                return ['ssid' => $ssid, 'password' => $encrypted];
            })
            ->filter()
            ->values()
            ->all();

        unset($data['wifi_networks_input']);

        $meta = $this->record->meta ?? [];
        $meta['wifi_networks'] = $networks;
        $data['meta'] = $meta;

        return $data;
    }
}
