<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use App\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\{TextInput, Toggle, Textarea, Select};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class WorkStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'workSteps';
    protected static ?string $title = 'Munkalépések (recept)';

    /** Csak késztermékeknél jelenjen meg */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ($ownerRecord->kind ?? null) === 'kesztermek';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('step_no')->label('Sorszám')->numeric()->minValue(1),

            // 1) Művelet workflow-ból
            Select::make('workflow_id')
                ->label('Művelet (workflow)')
                ->relationship('workflow', 'name') // ha nem 'name' a mező, jelezd
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $wf = Workflow::find($state);
                    // kitöltjük/cachelünk egy megjelenítési nevet
                    $set('name', $wf?->name ?? null);
                }),

            // Opciós cache-mező (látható, de nem szerkeszthető)
            TextInput::make('name')->label('Művelet neve (cache)')
                ->disabled()
                ->dehydrated(true),

            // 2) Több gép kiválasztása
            Select::make('machines')
                ->label('Gépek (képesek a műveletre)')
                ->relationship('machines', 'name') // machines.name
                ->multiple()
                ->preload()
                ->searchable(),

            TextInput::make('cycle_time_sec')->label('Ciklusidő (mp/db)')
                ->numeric()->step('0.001')->minValue(0.001)->required(),

            TextInput::make('setup_time_sec')->label('Beállási idő (mp)')
                ->numeric()->step('0.001')->minValue(0)->default(0),

            Toggle::make('is_active')
                ->label('Aktív')
                
                ->default(true),

            Textarea::make('notes')->label('Megjegyzés')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('step_no')
            ->defaultSort('step_no')
            ->columns([
                TextColumn::make('step_no')->label('Sorszám')->sortable(),
                TextColumn::make('workflow.name')->label('Workflow'),
                TextColumn::make('name')->label('Művelet'),
                TextColumn::make('machines.name')  // több gép listázva
                    ->label('Gépek')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cycle_time_sec')
                    ->label('Ciklus (mp/db)')
                    ->numeric(3)
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' ')),
                TextColumn::make('setup_time_sec')
                    ->label('Beállás (mp)')
                    ->numeric(3)
                    ->sortable()
                    ->alignRight()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        $s = (int) round((float) $state);
                        $h = intdiv($s, 3600);
                        $m = intdiv($s % 3600, 60);
                        $sec = $s % 60;
                        return sprintf('%02d:%02d:%02d', $h, $m, $sec);
                    }),
                TextColumn::make('is_active')->label('Aktív')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Igen' : 'Nem')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var \App\Models\Item $owner */
                        $owner = $this->getOwnerRecord();

                        $data['item_id']    = $owner->id;
                        $data['company_id'] = $owner->company_id ?? null;

                        if (empty($data['step_no'])) {
                            $data['step_no'] = (int) $owner->workSteps()->max('step_no') + 1;
                        }

                        // ha nincs külön megadva a név, a workflow nevét cache-eljük
                        if (empty($data['name']) && !empty($data['workflow_id'])) {
                            $data['name'] = optional(Workflow::find($data['workflow_id']))->name;
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var \App\Models\Item $owner */
                        $owner = $this->getOwnerRecord();
                        $data['company_id'] = $owner->company_id ?? null;

                        if (empty($data['name']) && !empty($data['workflow_id'])) {
                            $data['name'] = optional(Workflow::find($data['workflow_id']))->name;
                        }
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([ Tables\Actions\DeleteBulkAction::make() ]);
    }
}
