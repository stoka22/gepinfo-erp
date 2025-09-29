<?php

namespace App\Filament\Resources\PartnerOrderResource\RelationManagers;

use App\Models\Item;
use App\Models\PartnerOrderItem;
use App\Models\ProductionSplit;
use Filament\Forms\Components\{Select, TextInput, DatePicker};
use Filament\Forms\{Form, Get, Set};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;

use App\Services\Scheduling\BuildTasksFromItemWorkSteps;
use App\Models\ItemWorkStep;

use Filament\Tables\Columns\Summarizers\Sum;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Tételek';


    
    public function form(Form $form): Form
    {
        // Szülő megrendelés (PartnerOrder) – ebből kell a partner_id
        $order = $this->getOwnerRecord();

        return $form->schema([
            Select::make('item_id')
                ->label('Késztermék (a partner korábbi rendelései alapján)')
                // ❶ Csak késztermékek + azon belül a partner korábban rendeltjei
                ->relationship(
                    name: 'item',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) use ($order) {
                        $query->where('kind', 'kesztermek')->where('is_active', 1);
                    // Ha multitenant a rendszered és van tenant (cég) a Filamentben:
                    if ($tenant = \Filament\Facades\Filament::getTenant()) {
                        $query->where('company_id', $tenant->id);
                    }

                        
                    }
                )
                // szebb opciócím (név + SKU)
                ->getOptionLabelFromRecordUsing(fn (Item $rec) =>
                    $rec->sku ? "{$rec->name} ({$rec->sku})" : $rec->name
                )
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                // ❷ Kiválasztás után: megnevezés cache + egység + utolsó rendelt darabszám
                ->afterStateUpdated(function ($state, Set $set) use ($order) {
                    $item = Item::find($state);
                    $set('item_name_cache', $item?->name ?? '');
                    $set('unit', $item?->unit ?? 'db');

                    // utolsó rendelt mennyiség ennél a partnernél erre az itemre
                    if ($order?->partner_id && $state) {
                        $lastQty = PartnerOrderItem::query()
                            ->select('partner_order_items.qty_ordered')
                            ->join('partner_orders as po', 'po.id', '=', 'partner_order_items.partner_order_id')
                            ->where('po.partner_id', $order->partner_id)
                            ->where('partner_order_items.item_id', $state)
                            ->latest('partner_order_items.created_at')
                            ->value('partner_order_items.qty_ordered');

                        if (!is_null($lastQty)) {
                            $set('qty_ordered', $lastQty);
                        }
                    }
                }),

            TextInput::make('item_name_cache')->label('Megnevezés')->disabled()->dehydrated(true),

            TextInput::make('unit')
                ->label('Egység')
                ->default('db')
                ->maxLength(10),

            TextInput::make('qty_ordered')
                ->label('Darabszám')
                ->numeric()
                ->required()
                ->minValue(1)
                ->reactive()
                ->afterStateUpdated(fn($state, Set $set, Get $get) =>
                    $set('line_total', (float)($get('unit_price') ?? 0) * (float)$state)
                )
                ->helperText(function (Get $get) use ($order) {
                    $itemId = $get('item_id');
                    if (!$itemId || !$order?->partner_id) return null;

                    $lastQty = PartnerOrderItem::query()
                        ->select('partner_order_items.qty_ordered')
                        ->join('partner_orders as po', 'po.id', '=', 'partner_order_items.partner_order_id')
                        ->where('po.partner_id', $order->partner_id)
                        ->where('partner_order_items.item_id', $itemId)
                        ->latest('partner_order_items.created_at')
                        ->value('partner_order_items.qty_ordered');

                    return $lastQty ? "Utoljára rendelt mennyiség: {$lastQty}" : null;
                }),

            TextInput::make('qty_reserved')->label('Foglalás')->numeric()->minValue(0)->default(0),

            TextInput::make('unit_price')
                ->label('Egységár')
                ->numeric()->minValue(0)->default(0)
                ->reactive()
                ->afterStateUpdated(fn($state, Set $set, Get $get) =>
                    $set('line_total', (float)$state * (float)($get('qty_ordered') ?? 0))
                ),

            TextInput::make('line_total')->label('Sor összesen')->numeric()->disabled(),

            DatePicker::make('due_date')->label('Soronkénti határidő'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.sku')->label('Késztermék')->searchable(),
                TextColumn::make('qty_ordered')
                    ->label('Rendelt')
                    
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                    ->alignRight()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Össz.')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                    ),
                TextColumn::make('qty_reserved')
                    ->label('Foglalva')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                    ->alignRight()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Össz.')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                    ),
                TextColumn::make('qty_produced')
                    ->label('Gyártott')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                    ->alignRight()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Össz.')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                    ),
                TextColumn::make('unit_price')->money('HUF')->label('Egységár'),
                TextColumn::make('line_total')->money('HUF')->label('Sor összesen'),
                TextColumn::make('status')->badge()->label('Állapot'),
            ])
           ->headerActions([
                Tables\Actions\CreateAction::make()
                    //->label('')
                    ->mutateFormDataUsing(function(array $data){
                        if (!empty($data['item_id']) && !empty($data['qty_ordered'])) {
                            $item = \App\Models\Item::with('workSteps')->find($data['item_id']);
                            if ($item) {
                                $secs = $item->estimatedDurationForQty((float)$data['qty_ordered']);
                                $data['est_duration_sec'] = $secs;
                                $data['est_finish_at'] = now()->addSeconds($secs);
                            }
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('generatePlanFromRecipe')
                ->label('Ütemterv generálása (receptből)')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation(false)
                ->form([
                    DateTimePicker::make('start')
                        ->label('Első lépés kezdése')
                        ->seconds(false)
                        ->default(now()->addHour())
                        ->required(),
                ])
                ->action(function (PartnerOrderItem $record, array $data) {
                    // Van-e aktív receptlépés a termékhez?
                    $hasSteps = ItemWorkStep::where('item_id', $record->item_id)
                        ->where('is_active', 1)
                        ->exists();

                    if (!$hasSteps) {
                        Notification::make()
                            ->title('Nincs recept a termékhez')
                            ->body('Ehhez a késztermékhez nem található aktív receptlépés (item_work_steps).')
                            ->danger()->send();
                        return;
                    }

                    // Ütemterv építése a receptből
                    $builder = app(BuildTasksFromItemWorkSteps::class);

                    try {
                        $res = $builder->handle($record->id, $data['start']);

                        Notification::make()
                            ->title('Ütemterv létrehozva')
                            ->body(count($res['tasks']).' feladat és '.count($res['dependencies']).' függőség készült.')
                            ->success()->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Hiba az ütemterv generálásakor')
                            ->body($e->getMessage())
                            ->danger()->send();
                    }
                }),

                Tables\Actions\EditAction::make()
                    ->label('')
                    ->mutateFormDataUsing(function (array $data, \App\Models\PartnerOrderItem $record) {
                        if (empty($data['item_name_cache']) && !empty($data['item_id'])) {
                            $item = \App\Models\Item::select('name','unit')->find($data['item_id']);
                            $data['item_name_cache'] = $item?->name ?? '';
                            $data['unit'] = $data['unit'] ?? ($item?->unit ?? 'db');
                        }
                        $data['line_total'] = (float)($data['unit_price'] ?? 0) * (float)($data['qty_ordered'] ?? 0);
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()->label(''),

                // Gyors rész-gyártás rögzítés gomb
                Tables\Actions\Action::make('addSplit')
                    ->label('Rész-gyártás hozzáadása')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->form([
                        TextInput::make('qty')->numeric()->minValue(0.001)->required(),
                        DatePicker::make('produced_at')->default(now())->required()->label('Gyártás dátuma'),
                        TextInput::make('notes')->label('Megjegyzés'),
                    ])
                    ->action(function (PartnerOrderItem $record, array $data) {
                        ProductionSplit::create([
                            'partner_order_item_id' => $record->id,
                            'qty'         => $data['qty'],
                            'produced_at' => $data['produced_at'],
                            'notes'       => $data['notes'] ?? null,
                        ]);
                        $record->refresh();
                    }),
            ])
            ->bulkActions([ Tables\Actions\DeleteBulkAction::make() ]);
    }
}
