<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Facades\Filament;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeVacationStats;
use App\Filament\Resources\EmployeeResource\RelationManagers\SkillsRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\VacationAllowancesRelationManager;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeLeaveCard;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeOvertimeCard;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeMonthlyHoursChart;

class EditEmployee extends EditRecord
{
    
    protected static string $resource = EmployeeResource::class;
    

   /* public static function getRelations(): array
    {
        return [
            SkillsRelationManager::class,
            TimeEntriesRelationManager::class,
            VacationAllowancesRelationManager::class,
        ];
    }*/

   

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Archiválás')
                ->requiresConfirmation()
                ->visible(fn ($record) => ! $record->trashed()),
            Actions\RestoreAction::make()->label('Visszaállítás')
                ->visible(fn ($record) => $record->trashed()),
            Actions\ForceDeleteAction::make()->label('Végleges törlés')
                ->requiresConfirmation()
                ->visible(fn ($record) =>
                    $record->trashed() && (Filament::auth()->user()?->role ?? null) === 'admin'
                ),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Filament::auth()->user();

        // Nem admin nem írhatja át a tulajt
        if (($user->role ?? null) !== 'admin') {
            $data['user_id'] = $this->record->user_id;
        }

        return $data;
    }

    /** Első betöltéskor töltsük be a pivotokat a repeater state-be. */
    protected function afterFill(): void
    {
        $this->record->loadMissing('skills');
        $this->setSkillsRepeaterState($this->buildSkillsState());
    }

    /** Mentés után frissítsük a kapcsolatot és csak a repeater állapotát írjuk vissza. */
    protected function afterSave(): void
    {
        $this->record->refresh()->load('skills');
        $this->setSkillsRepeaterState($this->buildSkillsState());
    }

    /** A kapcsolatból olyan struktúrát építünk, amit a Repeater (id + pivot.*) vár. */
    private function buildSkillsState(): array
    {
        return $this->record->skills->map(function ($skill) {
            return [
                'id' => $skill->id,
                'pivot' => [
                    'level'        => (int) ($skill->pivot->level ?? 0),
                    'certified_at' => $skill->pivot->certified_at
                        ? Carbon::parse($skill->pivot->certified_at)->toDateString()
                        : null,
                    'notes'        => $skill->pivot->notes,
                ],
            ];
        })->values()->toArray();
    }

    /**
     * Csak a 'skills' Repeater állapotát állítjuk.
     * A többi mezőt meghagyjuk az aktuális értékén.
     */
    private function setSkillsRepeaterState(array $state): void
    {
        // Kiolvassuk a teljes state-et, hozzáírjuk a skills-t, majd visszatöltjük
        $all = $this->form->getState();   // vagy getRawState() ha elérhető a verziódban
        $all['skills'] = $state;
        $this->form->fill($all);
    }

    protected function getHeaderWidgets(): array
    {
        return [
           EmployeeLeaveCard::class,
           EmployeeOvertimeCard::class,
           EmployeeMonthlyHoursChart::class,
        ];
    }

   public function getHeaderWidgetsColumns(): int | string | array
        {
            return [
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ];
        }

     
    
}
