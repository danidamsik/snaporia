<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $filters = $this->validatedFilters($request);

        $transactions = Transaction::query()
            ->with(['order.user:id,name,email', 'order.event:id,admin_id,name,date,location'])
            ->whereHas('order.event', fn ($query) => $query->where('admin_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('status', $status)))
            ->when($filters['event_id'] ?? null, fn ($query, string $eventId) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('event_id', $eventId)))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('midtrans_order_id', 'like', "%{$keyword}%")
                        ->orWhere('midtrans_transaction_id', 'like', "%{$keyword}%")
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_code', 'like', "%{$keyword}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => $this->transactionPayload($transaction, $request));

        return Inertia::render('Transactions/Index', [
            'title' => 'Transaksi',
            'transactions' => $transactions,
            'filters' => $this->filterPayload($filters),
            'events' => Event::query()
                ->where('admin_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'admins' => [],
            'canDelete' => false,
            'routeBase' => 'admin.transactions',
        ]);
    }

    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['order.user:id,name,email', 'order.event:id,admin_id,name,date,location', 'order.items.photo:id,filename,file_size,mime_type']);

        return Inertia::render('Transactions/Show', [
            'transaction' => $this->transactionDetailPayload($transaction, $request),
            'backUrl' => route('admin.transactions.index'),
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in([Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_FAILED, Order::STATUS_EXPIRED])],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function filterPayload(array $filters): array
    {
        return [
            'status' => $filters['status'] ?? '',
            'event_id' => $filters['event_id'] ?? '',
            'admin_id' => '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'q' => $filters['q'] ?? '',
        ];
    }

    private function transactionPayload(Transaction $transaction, Request $request): array
    {
        return [
            'id' => $transaction->id,
            'midtrans_order_id' => $transaction->midtrans_order_id,
            'midtrans_transaction_id' => $transaction->midtrans_transaction_id,
            'status' => $transaction->status,
            'order_status' => $transaction->order->status,
            'payment_type' => $transaction->payment_type,
            'gross_amount' => (float) $transaction->gross_amount,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'url' => route('admin.transactions.show', $transaction),
            'can_delete' => $request->user()->can('delete', $transaction),
            'order' => [
                'id' => $transaction->order->id,
                'order_code' => $transaction->order->order_code,
                'type' => $transaction->order->type,
                'total_amount' => (float) $transaction->order->total_amount,
            ],
            'event' => [
                'id' => $transaction->order->event->id,
                'name' => $transaction->order->event->name,
            ],
            'user' => [
                'id' => $transaction->order->user->id,
                'name' => $transaction->order->user->name,
                'email' => $transaction->order->user->email,
            ],
            'admin' => null,
        ];
    }

    private function transactionDetailPayload(Transaction $transaction, Request $request): array
    {
        return [
            ...$this->transactionPayload($transaction, $request),
            'fraud_status' => $transaction->fraud_status,
            'payment_url' => $transaction->payment_url,
            'expires_at' => $transaction->expires_at?->toIso8601String(),
            'payload' => $transaction->payload,
            'order' => [
                'id' => $transaction->order->id,
                'order_code' => $transaction->order->order_code,
                'type' => $transaction->order->type,
                'status' => $transaction->order->status,
                'total_amount' => (float) $transaction->order->total_amount,
                'expires_at' => $transaction->order->expires_at?->toIso8601String(),
                'paid_at' => $transaction->order->paid_at?->toIso8601String(),
            ],
            'items' => $transaction->order->items
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'filename' => $item->photo->filename,
                    'file_size' => $item->photo->file_size,
                    'mime_type' => $item->photo->mime_type,
                    'price' => (float) $item->price,
                ])
                ->values(),
        ];
    }
}
