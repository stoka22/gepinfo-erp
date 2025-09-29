<?php

namespace App\Filament\Resources\ResourceShiftAssignmentResource\Pages;

use App\Filament\Resources\ResourceShiftAssignmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;

use App\Models\Machine;
use App\Models\ShiftPattern;
use App\Models\ResourceShiftAssignment;
use Illuminate\Support\Facades\DB;

class ListResourceShiftAssignments extends ListRecords
{
    protected static string $resource = ResourceShiftAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Új hozzárendelés'),

            // --- ÚJ: Tömeges kiosztás több gépre ---
            Actions\Action::make('bulkAssign')
                ->label('Tömeges hozzárendelés')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('machine_ids')
                        ->label('Gépek')
                        ->options(fn () => Machine::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('shift_pattern_id')
                        ->label('Műszak minta')
                        ->options(function () {
                            $daysMap = ['Vas','Hét','Ked','Sze','Csü','Pén','Szo'];
                            return ShiftPattern::query()
                                ->orderBy('start_time')->orderBy('name')
                                ->get()
                                ->mapWithKeys(function ($p) use ($daysMap) {
                                    $labels = [];
                                    for ($i = 0; $i <= 6; $i++) {
                                        if (($p->days_mask & (1 << $i)) !== 0) $labels[] = $daysMap[$i];
                                    }
                                    $daysStr = implode(',', $labels) ?: '—';
                                    return [$p->id => "{$p->name} ({$daysStr} {$p->start_time}-{$p->end_time})"];
                                });
                        })
                        ->searchable()
                        ->required(),

                    Forms\Components\DatePicker::make('valid_from')->label('Érvényes ettől')->required(),
                    Forms\Components\DatePicker::make('valid_to')->label('Érvényes eddig')->helperText('Üres = nyílt vég'),

                    Forms\Components\Toggle::make('overwrite')
                        ->label('Ütköző időszakok felülírása')
                        ->helperText('Ha be van kapcsolva, a megadott időszakban töröljük az adott gépre vonatkozó korábbi mintákat.')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $machineIds = $data['machine_ids'] ?? [];
                    $patternId  = (int) $data['shift_pattern_id'];
                    $from       = $data['valid_from'];
                    $to         = $data['valid_to'] ?? null;
                    $overwrite  = (bool) ($data['overwrite'] ?? false);

                    if (empty($machineIds)) {
                        Notification::make()->title('Nincs kiválasztott gép')->danger()->send();
                        return;
                    }

                    DB::transaction(function () use ($machineIds, $patternId, $from, $to, $overwrite) {
                        foreach ($machineIds as $mid) {
                            if ($overwrite) {
                                // Töröljük a metsző időszakokat ugyanarra a gépre
                                ResourceShiftAssignment::where('resource_id', $mid)
                                    ->where(function ($q) use ($from, $to) {
                                        // átfedés logika: (a<=to || to is null) && (b>=from)
                                        $q->where(function ($qq) use ($from) {
                                            $qq->whereNull('valid_to')->orWhere('valid_to', '>=', $from);
                                        });
                                        if ($to) $q->where('valid_from', '<=', $to);
                                    })
                                    ->delete();
                            }

                            ResourceShiftAssignment::create([
                                'resource_id'     => $mid,
                                'shift_pattern_id'=> $patternId,
                                'valid_from'      => $from,
                                'valid_to'        => $to,
                            ]);
                        }
                    });

                    Notification::make()
                        ->title('Hozzárendelés kész')
                        ->body(count($machineIds).' gépre beállítva a választott műszakminta.')
                        ->success()->send();
                }),
        ];
    }
}
