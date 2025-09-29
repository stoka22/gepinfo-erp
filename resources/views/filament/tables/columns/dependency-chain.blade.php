@php
    /** @var \App\Models\TaskDependency $rec */
    $rec = $getRecord();   // <-- EZ a lényeg

    $orderItemId = optional($rec->predecessor)->order_item_id
        ?? optional($rec->successor)->order_item_id;

    $deps = \App\Models\TaskDependency::query()
        ->with([
            'predecessor','successor',
            'predecessor.orderItem.item','successor.orderItem.item',
        ])
        ->when($orderItemId, fn($q) =>
            $q->whereHas('predecessor', fn($qq) => $qq->where('order_item_id', $orderItemId))
        )
        ->orderBy('id')
        ->get();

    $productName = optional(optional($deps->first())->predecessor->orderItem->item)->name
        ?? optional($rec->predecessor->orderItem->item)->name
        ?? 'Ismeretlen termék';
@endphp

<div x-show="open" x-cloak class="p-3 bg-gray-900/40 rounded-lg border border-gray-800 mt-2">
    <div class="text-sm text-gray-400 mb-2">
        Termék: <span class="font-semibold">{{ $productName }}</span>
        @if($orderItemId)
            <span class="ml-2 text-xs text-gray-500">(#{{ $orderItemId }})</span>
        @endif
    </div>

    <table class="w-full text-sm">
        <thead class="text-gray-400">
            <tr>
                <th class="text-left py-1 pr-2">Elődfeladat</th>
                <th class="text-left py-1 pr-2">→</th>
                <th class="text-left py-1 pr-2">Utódfeladat</th>
                <th class="text-left py-1 pr-2">Lag</th>
            </tr>
        </thead>
        <tbody>
        @forelse($deps as $d)
            <tr class="border-t border-gray-800">
                <td class="py-1 pr-2">
                    {{ $d->predecessor?->name }}
                    <span class="text-xs text-gray-500">
                        ({{ optional($d->predecessor?->starts_at)->format('m.d H:i') }}–{{ optional($d->predecessor?->ends_at)->format('H:i') }})
                    </span>
                </td>
                <td class="py-1 pr-2">FS</td>
                <td class="py-1 pr-2">
                    {{ $d->successor?->name }}
                    <span class="text-xs text-gray-500">
                        ({{ optional($d->successor?->starts_at)->format('m.d H:i') }}–{{ optional($d->successor?->ends_at)->format('H:i') }})
                    </span>
                </td>
                <td class="py-1 pr-2">{{ $d->lag_minutes }} perc</td>
            </tr>
        @empty
            <tr><td colspan="4" class="py-2 text-gray-400">Nincs láncelem ehhez a termékhez.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
