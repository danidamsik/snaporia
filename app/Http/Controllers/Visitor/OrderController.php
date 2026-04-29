<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()
            ->orders()
            ->with('event:id,name,date,location')
            ->withCount('items')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order) => $this->orderPayload($order));

        return Inertia::render('Visitor/Orders/Index', [
            'orders' => $orders,
            'title' => 'Riwayat Pembelian',
            'emptyMessage' => 'Order akan muncul setelah kamu membeli foto dari galeri.',
        ]);
    }

    public function downloads(Request $request): Response
    {
        $orders = $request->user()
            ->orders()
            ->with('event:id,name,date,location')
            ->withCount('items')
            ->where('status', Order::STATUS_PAID)
            ->latest('paid_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order) => $this->orderPayload($order));

        return Inertia::render('Visitor/Orders/Index', [
            'orders' => $orders,
            'title' => 'Download Saya',
            'emptyMessage' => 'Belum ada order paid yang bisa diunduh.',
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        if ($order->status === Order::STATUS_PENDING && $order->expires_at && $order->expires_at->isPast()) {
            $order->update(['status' => Order::STATUS_EXPIRED]);
            $order->refresh();
        }

        $order->load('event:id,name,date,location,price_per_photo,price_package');

        $items = $order->items()
            ->with('photo:id,event_id,filename,file_size,mime_type,sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($item) => [
                'id' => $item->id,
                'photo_id' => $item->photo->id,
                'filename' => $item->photo->filename,
                'file_size' => $item->photo->file_size,
                'mime_type' => $item->photo->mime_type,
                'price' => (float) $item->price,
                'download_url' => $order->status === Order::STATUS_PAID
                    ? route('visitor.orders.photos.download', [$order, $item->photo])
                    : null,
            ]);

        return Inertia::render('Visitor/Orders/Show', [
            'order' => [
                ...$this->orderPayload($order),
                'event' => [
                    'id' => $order->event->id,
                    'name' => $order->event->name,
                    'date' => $order->event->date?->toDateString(),
                    'location' => $order->event->location,
                    'url' => route('events.show', $order->event),
                ],
            ],
            'items' => $items,
        ]);
    }

    public function download(Order $order, Photo $photo): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $order);

        if ($order->status !== Order::STATUS_PAID) {
            return back()->with('error', 'Download hanya tersedia untuk order paid.');
        }

        $hasPhoto = $order->items()->where('photo_id', $photo->id)->exists();
        if (! $hasPhoto) {
            abort(404);
        }

        if (Storage::disk('local')->missing($photo->original_path)) {
            Log::warning('Original photo download failed because file is missing.', [
                'order_id' => $order->id,
                'photo_id' => $photo->id,
            ]);

            return back()->with('error', 'File original tidak ditemukan. Hubungi admin Snaporia.');
        }

        return Storage::disk('local')->download($photo->original_path, $this->downloadName($order, $photo));
    }

    private function orderPayload(Order $order): array
    {
        $transaction = $order->transactions()->latest()->first();

        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'type' => $order->type,
            'status' => $order->status,
            'total_amount' => (float) $order->total_amount,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'items_count' => $order->items_count ?? $order->items()->count(),
            'url' => route('visitor.orders.show', $order),
            'pay_url' => route('payment.orders.pay', $order),
            'refresh_url' => route('payment.orders.refresh', $order),
            'payment' => $transaction ? $this->paymentPayload($transaction) : null,
            'midtrans' => $this->midtransPayload(),
            'event' => [
                'id' => $order->event->id,
                'name' => $order->event->name,
                'date' => $order->event->date?->toDateString(),
                'location' => $order->event->location,
            ],
        ];
    }

    private function paymentPayload($transaction): array
    {
        return [
            'status' => $transaction->status,
            'snap_token' => $transaction->snap_token,
            'payment_url' => $transaction->payment_url,
            'payment_type' => $transaction->payment_type,
            'gross_amount' => (float) $transaction->gross_amount,
            'expires_at' => $transaction->expires_at?->toIso8601String(),
        ];
    }

    private function midtransPayload(): array
    {
        return [
            'client_key' => config('midtrans.client_key'),
            'snap_js_url' => config('midtrans.is_production')
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ];
    }

    private function downloadName(Order $order, Photo $photo): string
    {
        $extension = pathinfo($photo->filename, PATHINFO_EXTENSION);
        $baseName = pathinfo($photo->filename, PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName) ?: 'photo';

        return $order->order_code.'-'.$safeBaseName.($extension ? '.'.$extension : '');
    }
}
