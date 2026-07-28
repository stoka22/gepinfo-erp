<?php

namespace App\Filament\Widgets;

use App\Models\VacationBalance;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class VacationRebuildWidget extends Widget implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.vacation-rebuild';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    /** Évenkénti szabadságkeret-összesítő (utoljára frissítve, hány dolgozóra van adat). */
    public function getYearsSummary(): array
    {
        return VacationBalance::query()
            ->selectRaw('year, count(*) as employee_count, max(updated_at) as last_updated')
            ->groupBy('year')
            ->orderByDesc('year')
            ->limit(5)
            ->get()
            ->all();
    }

    public function rebuildVacationAction(): Action
    {
        return Action::make('rebuildVacation')
            ->label('Szabadságkeret újraszámolása')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->form([
                TextInput::make('year')
                    ->label('Év')
                    ->numeric()
                    ->default(now()->year)
                    ->minValue(2020)
                    ->maxValue(2100)
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalDescription('Minden dolgozó szabadságkeretét (alap + életkor szerinti pótnap) újraszámolja a megadott évre. A göngyölt/kézi módosítás mezőket nem érinti. Ugyanezt minden deploy automatikusan lefuttatja a jelenlegi évre.')
            ->action(function (array $data) {
                $year = (int) $data['year'];

                Artisan::call('vacation:rebuild', ['year' => $year]);
                $output = trim(Artisan::output());

                Notification::make()
                    ->title('Szabadságkeret újraszámolva')
                    ->body($output !== '' ? $output : "Kész: {$year}.")
                    ->success()
                    ->send();
            });
    }
}
