<?php

namespace App\Http\Controllers\Scheduler;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemApiController extends Controller
{
    public function __invoke(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = Item::query()
            ->select(['id', 'name', 'sku', 'unit'])
            ->where('is_active', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(100)->get()
        );
    }
}
