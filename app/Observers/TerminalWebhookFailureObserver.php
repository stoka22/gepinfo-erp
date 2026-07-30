<?php

namespace App\Observers;

use App\Filament\Resources\TerminalWebhookFailureResource;
use App\Models\TerminalWebhookFailure;
use App\Models\User;
use Filament\Notifications\Notification;

class TerminalWebhookFailureObserver
{
    /**
     * Minden új webhook-hiba beérkezésekor adatbázis-értesítést küld az admin felhasználóknak,
     * hogy a felső navbár haranga jelezze — ne kelljen a felületet manuálisan nyitva tartani
     * egy fizikai terminál hibás/rosszul konfigurált kéréseinek észrevételéhez.
     */
    public function created(TerminalWebhookFailure $failure): void
    {
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Webhook hiba érkezett')
            ->body($this->describe($failure))
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Megnyitás')
                    ->url(TerminalWebhookFailureResource::getUrl('view', ['record' => $failure])),
            ])
            ->sendToDatabase($admins);
    }

    protected function describe(TerminalWebhookFailure $failure): string
    {
        $label = match ($failure->error_code) {
            'unauthorized'   => 'Érvénytelen token',
            'unknown_card'   => 'Ismeretlen kártya',
            'no_open_entry'  => 'Nincs nyitott belépés',
            'validation'     => 'Validációs hiba',
            'no_system_user' => 'Nincs rendszerfelhasználó',
            default          => $failure->error_code,
        };

        return $failure->card_uid
            ? "{$label} (kártya: {$failure->card_uid})"
            : $label;
    }
}
