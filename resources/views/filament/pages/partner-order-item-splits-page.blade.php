<x-filament-panels::page>
    <div class="space-y-4">
        <div class="text-sm text-gray-500">
            <div>
                <strong>Rendelés:</strong> {{ $this->orderItem->order->order_no ?? '#' }}
            </div>
            <div>
                <strong>Tétel:</strong>
                {{ $this->orderItem->item_name_cache ?? ($this->orderItem->item->name ?? '—') }}
            </div>
            <div>
                <strong>Rendelt:</strong> {{ number_format($this->orderItem->qty_ordered, 3, ',', ' ') }}
                &nbsp;|&nbsp;
                <strong>Gyártott:</strong> {{ number_format($this->orderItem->qty_produced, 3, ',', ' ') }}
                &nbsp;|&nbsp;
                <strong>Hátralévő:</strong>
                {{ number_format(max(0, $this->orderItem->qty_ordered - $this->orderItem->qty_produced), 3, ',', ' ') }}
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
