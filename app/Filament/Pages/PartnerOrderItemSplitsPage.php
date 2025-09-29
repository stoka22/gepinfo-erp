<?php

namespace App\Filament\Pages;

use App\Models\PartnerOrderItem;
use App\Models\ProductionSplit;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;

class PartnerOrderItemSplitsPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $slug = 'order-items/{record}/splits';
    protected static string $view = 'filament.pages.partner-order-item-splits-page';

    /** @var int|string */
    public $record;

    public ?PartnerOrderItem $orderItem = null;

    public function mount(int|string $record): void
    {
        $this->record = $record;
        $this->orderItem = PartnerOrderItem::with(['order', 'item'])->findOrFail($record);
    }

    /** Dinamikus cím – ne static::$title-t írd, hanem ezt add vissza. */
    public function getTitle(): string
    {
        $orderNo = $this->orderItem->order->order_no ?? '#';
        $itemName = $this->orderItem->item_name_cache ?? ($this->orderItem->item->name ?? 'Tétel');

        return "Gyártási szakaszok — {$orderNo} / {$itemName}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(static::getRouteName(), ['record' => $this->record]) => 'Gyártási szakaszok',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder =>
                ProductionSplit::query()
                    ->where('partner_order_item_id', $this->record)
                    ->latest('produced_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('produced_at')->label('Dátum')->date(),
                Tables\Columns\TextColumn::make('qty')->label('Mennyiség'),
                Tables\Columns\TextColumn::make('notes')->label('Megjegyzés')->limit(60)->tooltip(fn ($r) => $r->notes),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Rögzítve'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Új rész-gyártás')
                    ->using(function (array $data) {
                        $data['partner_order_item_id'] = $this->record;
                        return ProductionSplit::create($data);
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $row = $this->orderItem->fresh(['splits']);
                        $remaining = (float)$row->qty_ordered - (float)$row->qty_produced;
                        if (($data['qty'] ?? 0) > $remaining) {
                            validator($data, [
                                'qty' => "max:{$remaining}",
                            ], [
                                "qty.max" => "Nem haladhatja meg a hátralévő mennyiséget ({$remaining}).",
                            ])->validate();
                        }
                        return $data;
                    })
                    ->after(fn () => $this->orderItem->refresh())
                    ->form([
                        Forms\Components\TextInput::make('qty')
                            ->numeric()->minValue(0.001)->required()
                            ->helperText(function () {
                                $row = $this->orderItem->fresh(['splits']);
                                $remaining = (float)$row->qty_ordered - (float)$row->qty_produced;
                                return "Hátralévő: {$remaining}";
                            }),
                        Forms\Components\DatePicker::make('produced_at')->default(now())->required()->label('Gyártás dátuma'),
                        Forms\Components\Textarea::make('notes')->label('Megjegyzés')->rows(3),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, ProductionSplit $record): array {
                        $row = $this->orderItem->fresh(['splits']);
                        $producedWithoutThis = (float)$row->splits()->where('id', '!=', $record->id)->sum('qty');
                        $max = max(0, (float)$row->qty_ordered - $producedWithoutThis);

                        if (($data['qty'] ?? 0) > $max) {
                            validator($data, [
                                'qty' => "max:{$max}",
                            ], [
                                "qty.max" => "Nem haladhatja meg a hátralévő mennyiséget ({$max}).",
                            ])->validate();
                        }
                        return $data;
                    })
                    ->after(fn () => $this->orderItem->refresh()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->orderItem->refresh()),
            ])
            ->emptyStateHeading('Még nincs rész-gyártás')
            ->emptyStateDescription('Hozd létre az első rész-gyártást a fenti gombbal.');
    }
}
