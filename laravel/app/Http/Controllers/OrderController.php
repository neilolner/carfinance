<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ]);

        // Eager load relations (3 queries total) instead of lazy loading per order (1 + 2N).
        // Foreign keys must stay in the column lists or the eager-load mapping breaks.
        // Cursor pagination keys on id: no OFFSET scan and no COUNT(*), so deep pages stay fast.
        $orders = Order::query()
            ->select(['id', 'customer_id', 'status', 'total'])
            ->with([
                'customer:id,name,email',
                'items:id,order_id,product_name,sku,quantity,unit_price',
            ])
            ->orderBy('id')
            ->cursorPaginate($validated['per_page'] ?? null)
            ->withQueryString();

        return OrderResource::collection($orders);
    }
}
