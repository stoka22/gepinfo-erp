<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Item;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDailyDigest extends Command
{
    protected $signature = 'digest:daily';

    protected $description = 'Napi összefoglaló admin értesítés: felülvizsgálandó jelenlétek, alacsony készlet, offline eszközök.';

    public function handle(): int
    {
        $needsReviewCount = TimeEntry::where('needs_review', true)->count();

        $lowStockItems = Item::query()
            ->whereNotNull('min_qty')
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'min_qty'])
            ->filter(function (Item $item) {
                $stock = DB::table('stock_levels')->where('item_id', $item->id)->sum('qty');
                return $stock < (float) $item->min_qty;
            })
            ->values();

        $offlineDevicesCount = Device::query()
            ->where(function ($q) {
                $timeout = (int) config('devices.online_timeout', 60);
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds($timeout));
            })
            ->count();

        if ($needsReviewCount === 0 && $lowStockItems->isEmpty() && $offlineDevicesCount === 0) {
            $this->info('Nincs jelentendő tétel, nem küldök értesítést.');
            return self::SUCCESS;
        }

        $bodyLines = [];
        if ($needsReviewCount > 0) {
            $bodyLines[] = "Felülvizsgálandó jelenlét: {$needsReviewCount} db";
        }
        if ($lowStockItems->isNotEmpty()) {
            $names = $lowStockItems->take(5)->map(fn (Item $i) => $i->name)->implode(', ');
            $more = $lowStockItems->count() > 5 ? ' (+'.($lowStockItems->count() - 5).' további)' : '';
            $bodyLines[] = "Alacsony készlet: {$lowStockItems->count()} tétel — {$names}{$more}";
        }
        if ($offlineDevicesCount > 0) {
            $bodyLines[] = "Offline eszköz: {$offlineDevicesCount} db";
        }

        $admins = User::query()
            ->where('role', 'admin')
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->get();

        if ($admins->isEmpty()) {
            $this->warn('Nincs admin felhasználó, nem tudok kinek küldeni.');
            return self::SUCCESS;
        }

        $notification = Notification::make()
            ->title('Napi összefoglaló')
            ->body(implode("\n", $bodyLines))
            ->warning()
            ->toDatabase();

        // notifyNow(): a Filament DatabaseNotification ShouldQueue-t implementál, worker nélkül
        // (pl. megosztott tárhelyen) sosem futna le a jobs táblából - itt szándékosan azonnali.
        foreach ($admins as $admin) {
            $admin->notifyNow($notification);
        }

        $this->info('Napi összefoglaló elküldve '.$admins->count().' admin felhasználónak.');

        return self::SUCCESS;
    }
}
