<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Item;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    #[\Override]
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Összes'),

            'alkatresz' => Tab::make('Alkatrész')
                ->modifyQueryUsing(fn (): Builder =>
                    Item::query()->where('kind', 'alkatresz')
                ),

            'alapanyag' => Tab::make('Alapanyag')
                ->modifyQueryUsing(fn (): Builder =>
                    Item::query()->where('kind', 'alapanyag')
                ),

            'kesztermek' => Tab::make('Késztermék')
                ->modifyQueryUsing(fn (): Builder =>
                    Item::query()->where('kind', 'kesztermek')
                ),
        ];
    }
}
