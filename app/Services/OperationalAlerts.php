<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Item;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Egy helyen tartja azokat a "figyelmeztető" mutatókat (felülvizsgálandó jelenlét,
 * alacsony készlet, offline eszköz), amiket mind a napi email-összefoglaló
 * (SendDailyDigest), mind a vezérlőpult KPI-csíkja használ — hogy a kettő sose
 * mondjon egymásnak ellentmondó számot ugyanarra a dologra.
 */
class OperationalAlerts
{
    public function needsReviewCount(): int
    {
        return TimeEntry::where('needs_review', true)->count();
    }

    /** @return Collection<int, Item> */
    public function lowStockItems(): Collection
    {
        return Item::query()
            ->whereNotNull('min_qty')
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'min_qty'])
            ->filter(function (Item $item) {
                $stock = DB::table('stock_levels')->where('item_id', $item->id)->sum('qty');
                return $stock < (float) $item->min_qty;
            })
            ->values();
    }

    public function offlineDevicesCount(): int
    {
        return Device::query()
            ->where(function ($q) {
                $timeout = (int) config('devices.online_timeout', 60);
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds($timeout));
            })
            ->count();
    }
}
