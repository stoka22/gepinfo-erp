<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Skill;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;

class SkillsRelationManager extends RelationManager
{
    
    protected static string $relationship = 'skills';
    protected static string $slug = 'skills';          // opcionális, de hasznos
    protected static ?string $title = 'Skills';
    protected static ?string $recordTitleAttribute = 'name';
    

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            // FONTOS: csak a skills.* legyen kiválasztva – így minden sor megjelenik.
            //->modifyQueryUsing(fn (Builder $query) => $query->select('skills.*'))
          /*  ->modifyQueryUsing(function (Builder $q) use ($pivot) {
                $q->select('skills.*')->addSelect([
                    "{$pivot}.level as pivot_level",
                    "{$pivot}.certified_at as pivot_certified_at",
                    "{$pivot}.notes as pivot_notes",
                ]);
            })*/
            ->defaultSort('name')

            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Skill')->sortable()->searchable(),

                Tables\Columns\TextColumn::make('pivot.level')
                    ->label('Szint')->sortable(),

                Tables\Columns\TextColumn::make('pivot.certified_at')
                    ->label('Vizsga dátuma')->date('Y. m. d.')->sortable(),

                Tables\Columns\TextColumn::make('pivot.notes')
                    ->label('Megjegyzés')->wrap(),
            ])

            ->headerActions([
                // Új skill csatolása – a mezők a pivotba kerülnek
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name'])
                    ->form(fn (AttachAction $action) => [
                        $action->getRecordSelect()->label('Skill'),

                        Forms\Components\TextInput::make('level')
                            ->label('Szint')->numeric()->minValue(1)->maxValue(5)->required(),

                        Forms\Components\DatePicker::make('certified_at')
                            ->label('Vizsga dátuma'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Megjegyzés')->columnSpanFull(),
                    ]),
            ])

            ->actions([
                // PIVOT szerkesztés – a formot mi töltjük és a pivotot frissítjük
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->recordTitle(fn (Skill $r) => $r->name)
                    ->modalHeading(fn (Skill $r) => 'Szerkesztés – '.$r->name)
                    ->form([
                        Forms\Components\TextInput::make('level')
                            ->label('Szint')->numeric()->minValue(1)->maxValue(5)->required(),

                        Forms\Components\DatePicker::make('certified_at')
                            ->label('Vizsga dátuma'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Megjegyzés')->columnSpanFull(),
                    ])
                    // a modál mezőinek előtöltése a pivotból
                    ->mountUsing(function (Skill $record, Forms\Form $form) {
                        $form->fill([
                            'level'        => $record->pivot->level,
                            'certified_at' => $record->pivot->certified_at,
                            'notes'        => $record->pivot->notes,
                        ]);
                    })
                    // mentés a pivot táblába
                    ->using(function (Skill $record, array $data): void {
                        $record->employees()->updateExistingPivot(
                            $this->ownerRecord->getKey(),
                            [
                                'level'        => (int) ($data['level'] ?? 0),
                                'certified_at' => $data['certified_at'] ?? null,
                                'notes'        => $data['notes'] ?? null,
                            ]
                        );
                    }),

                Tables\Actions\DetachAction::make()->label(''),
            ])

            // (opcionális) szűrők – mindig a pivot tábla nevét írd!
            ->filters([
                Tables\Filters\SelectFilter::make('pivot_level')
                    ->label('Szint')
                    ->options([1=>1,2=>2,3=>3,4=>4,5=>5])
                    ->query(fn (Builder $q, $state) =>
                        filled($state) ? $q->where('employee_skill.level', (int) $state) : $q
                    ),
            ]);
    }
}
