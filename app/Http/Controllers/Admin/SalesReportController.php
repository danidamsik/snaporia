<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $baseQuery = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereHas('event', fn ($query) => $query->where('admin_id', $request->user()->id))
            ->when($filters['event_id'] ?? null, fn ($query, string $eventId) => $query->where('event_id', $eventId))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('paid_at', '<=', $date));

        $orders = (clone $baseQuery)
            ->with(['event:id,name,date,location'])
            ->withCount('items')
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'type' => $order->type,
                'event' => [
                    'id' => $order->event->id,
                    'name' => $order->event->name,
                ],
                'items_count' => $order->items_count,
                'total_amount' => (float) $order->total_amount,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Reports/Sales', [
            'orders' => $orders,
            'summary' => [
                'total_revenue' => (float) (clone $baseQuery)->sum('total_amount'),
                'paid_orders_count' => (clone $baseQuery)->count(),
                'photos_sold_count' => (clone $baseQuery)->withCount('items')->get()->sum('items_count'),
            ],
            'events' => Event::query()
                ->where('admin_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'event_id' => $filters['event_id'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
        ]);
    }
}
