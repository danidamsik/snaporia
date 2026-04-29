<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\PaymentTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CheckoutController extends Controller
{
    public function single(Request $request): Response|RedirectResponse
    {
        $photos = $this->validatedSinglePhotos($request);
        $event = $photos->first()->event;

        $duplicate = $this->findDuplicateSinglePurchase($request, $photos, $event);
        if ($duplicate) {
            return redirect()
                ->route('checkout.orders.show', $duplicate)
                ->with('warning', 'Kamu sudah memiliki pembelian paid untuk foto atau paket ini.');
        }

        return Inertia::render('Checkout/Show', [
            'checkout' => $this->previewPayload(
                type: Order::TYPE_SINGLE,
                event: $event,
                photosCount: $photos->count(),
                totalAmount: $photos->count() * (float) $event->price_per_photo,
                storeUrl: route('checkout.single.store'),
                photoIds: $photos->pluck('id')->values()->all(),
            ),
        ]);
    }

    public function storeSingle(Request $request, PaymentTransactionService $payments): JsonResponse|RedirectResponse
    {
        $photos = $this->validatedSinglePhotos($request);
        $event = $photos->first()->event;

        $duplicate = $this->findDuplicateSinglePurchase($request, $photos, $event);
        if ($duplicate) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Kamu sudah memiliki pembelian paid untuk foto atau paket ini.',
                    'checkout' => $this->orderPayload($duplicate),
                ], 409);
            }

            return redirect()
                ->route('checkout.orders.show', $duplicate)
                ->with('warning', 'Kamu sudah memiliki pembelian paid untuk foto atau paket ini.');
        }

        $order = DB::transaction(function () use ($request, $photos, $event): Order {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_code' => $this->generateOrderCode(),
                'type' => Order::TYPE_SINGLE,
                'event_id' => $event->id,
                'total_amount' => $photos->count() * (float) $event->price_per_photo,
                'status' => Order::STATUS_PENDING,
                'expires_at' => $this->pendingExpiresAt(),
            ]);

            $order->items()->createMany(
                $photos->map(fn (Photo $photo) => [
                    'photo_id' => $photo->id,
                    'price' => $event->price_per_photo,
                ])->all()
            );

            return $order;
        });

        if ($request->expectsJson()) {
            return $this->createPaymentJson($order, $payments);
        }

        return redirect()
            ->route('checkout.orders.show', $order)
            ->with('success', 'Order pending berhasil dibuat.');
    }

    public function package(Request $request, Event $event): Response|RedirectResponse
    {
        $photos = $this->validatedPackagePhotos($event);

        $duplicate = $this->findDuplicatePackagePurchase($request, $event);
        if ($duplicate) {
            return redirect()
                ->route('checkout.orders.show', $duplicate)
                ->with('warning', 'Kamu sudah memiliki pembelian paid untuk paket event ini.');
        }

        return Inertia::render('Checkout/Show', [
            'checkout' => $this->previewPayload(
                type: Order::TYPE_PACKAGE,
                event: $event,
                photosCount: $photos->count(),
                totalAmount: (float) $event->price_package,
                storeUrl: route('checkout.package.store', $event),
            ),
        ]);
    }

    public function storePackage(Request $request, Event $event, PaymentTransactionService $payments): JsonResponse|RedirectResponse
    {
        $photos = $this->validatedPackagePhotos($event);

        $duplicate = $this->findDuplicatePackagePurchase($request, $event);
        if ($duplicate) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Kamu sudah memiliki pembelian paid untuk paket event ini.',
                    'checkout' => $this->orderPayload($duplicate),
                ], 409);
            }

            return redirect()
                ->route('checkout.orders.show', $duplicate)
                ->with('warning', 'Kamu sudah memiliki pembelian paid untuk paket event ini.');
        }

        $order = DB::transaction(function () use ($request, $event, $photos): Order {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_code' => $this->generateOrderCode(),
                'type' => Order::TYPE_PACKAGE,
                'event_id' => $event->id,
                'total_amount' => $event->price_package,
                'status' => Order::STATUS_PENDING,
                'expires_at' => $this->pendingExpiresAt(),
            ]);

            $order->items()->createMany(
                $photos->map(fn (Photo $photo) => [
                    'photo_id' => $photo->id,
                    'price' => 0,
                ])->all()
            );

            return $order;
        });

        if ($request->expectsJson()) {
            return $this->createPaymentJson($order, $payments);
        }

        return redirect()
            ->route('checkout.orders.show', $order)
            ->with('success', 'Order pending berhasil dibuat.');
    }

    public function showOrder(Order $order): Response
    {
        $this->authorize('view', $order);

        if ($order->status === Order::STATUS_PENDING && $order->expires_at && $order->expires_at->isPast()) {
            $order->update(['status' => Order::STATUS_EXPIRED]);
            $order->refresh();
        }

        return Inertia::render('Checkout/Show', [
            'checkout' => $this->orderPayload($order),
        ]);
    }

    private function createPaymentJson(Order $order, PaymentTransactionService $payments): JsonResponse
    {
        try {
            $result = $payments->createOrReuse($order);
        } catch (Throwable $exception) {
            Log::error('Midtrans payment creation failed after checkout order creation.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Order berhasil dibuat, tetapi pembayaran Midtrans belum bisa dibuka. Coba dari halaman detail order.',
                'checkout' => $this->orderPayload($order),
            ], 502);
        }

        return response()->json([
            'message' => 'Order berhasil dibuat. Pembayaran Midtrans siap dibuka.',
            'checkout' => $this->orderPayload($order),
            'payment' => $this->paymentPayload($result['transaction']),
        ], 201);
    }

    private function validatedSinglePhotos(Request $request): Collection
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:50'],
            'photos.*' => ['required', 'integer', 'distinct', 'exists:photos,id'],
        ]);

        $photoIds = collect($validated['photos'])->map(fn ($photoId) => (int) $photoId)->values();

        $photos = Photo::query()
            ->with('event')
            ->whereIn('id', $photoIds)
            ->get()
            ->sortBy(fn (Photo $photo) => $photoIds->search($photo->id))
            ->values();

        $eventIds = $photos->pluck('event_id')->unique();
        if ($eventIds->count() !== 1) {
            throw ValidationException::withMessages([
                'photos' => 'Checkout satuan hanya bisa berisi foto dari satu event yang sama.',
            ]);
        }

        $event = $photos->first()->event;
        if (! $event->is_published) {
            throw ValidationException::withMessages([
                'photos' => 'Event belum dipublikasikan sehingga belum bisa dibeli.',
            ]);
        }

        return $photos;
    }

    private function validatedPackagePhotos(Event $event): Collection
    {
        if (! $event->is_published) {
            throw ValidationException::withMessages([
                'event' => 'Event belum dipublikasikan sehingga belum bisa dibeli.',
            ]);
        }

        $photos = $event->photos()->orderBy('sort_order')->get();
        if ($photos->isEmpty()) {
            throw ValidationException::withMessages([
                'event' => 'Event belum memiliki foto sehingga belum bisa dibeli sebagai paket.',
            ]);
        }

        return $photos;
    }

    private function findDuplicateSinglePurchase(Request $request, Collection $photos, Event $event): ?Order
    {
        $paidPackage = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('event_id', $event->id)
            ->where('type', Order::TYPE_PACKAGE)
            ->where('status', Order::STATUS_PAID)
            ->first();

        if ($paidPackage) {
            return $paidPackage;
        }

        return Order::query()
            ->where('user_id', $request->user()->id)
            ->where('type', Order::TYPE_SINGLE)
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($query) => $query->whereIn('photo_id', $photos->pluck('id')))
            ->first();
    }

    private function findDuplicatePackagePurchase(Request $request, Event $event): ?Order
    {
        return Order::query()
            ->where('user_id', $request->user()->id)
            ->where('event_id', $event->id)
            ->where('type', Order::TYPE_PACKAGE)
            ->where('status', Order::STATUS_PAID)
            ->first();
    }

    private function previewPayload(
        string $type,
        Event $event,
        int $photosCount,
        float $totalAmount,
        string $storeUrl,
        array $photoIds = [],
    ): array {
        return [
            'mode' => 'preview',
            'type' => $type,
            'event' => $this->eventPayload($event),
            'photos_count' => $photosCount,
            'total_amount' => $totalAmount,
            'expires_at' => $this->pendingExpiresAt()->toIso8601String(),
            'store_url' => $storeUrl,
            'photo_ids' => $photoIds,
            'midtrans' => $this->midtransPayload(),
        ];
    }

    private function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date?->toDateString(),
            'location' => $event->location,
            'price_per_photo' => (float) $event->price_per_photo,
            'price_package' => (float) $event->price_package,
            'url' => route('events.show', $event),
        ];
    }

    private function orderPayload(Order $order): array
    {
        $order->loadMissing(['event', 'items.photo']);
        $transaction = $order->transactions()->latest()->first();

        return [
            'mode' => 'order',
            'order_code' => $order->order_code,
            'status' => $order->status,
            'type' => $order->type,
            'event' => $this->eventPayload($order->event),
            'photos_count' => $order->items->count(),
            'total_amount' => (float) $order->total_amount,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'pay_url' => route('payment.orders.pay', $order),
            'refresh_url' => route('payment.orders.refresh', $order),
            'payment' => $transaction ? $this->paymentPayload($transaction) : null,
            'photos' => $order->items
                ->map(fn ($item) => [
                    'id' => $item->photo->id,
                    'filename' => $item->photo->filename,
                    'price' => (float) $item->price,
                ])
                ->values(),
            'midtrans' => $this->midtransPayload(),
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

    private function pendingExpiresAt(): Carbon
    {
        $hours = (int) Setting::query()
            ->where('key', 'payment_pending_hours')
            ->value('value') ?: 24;

        return now()->addHours($hours);
    }

    private function generateOrderCode(): string
    {
        $sequence = Order::query()->whereDate('created_at', today())->count() + 1;

        do {
            $code = 'SNP-'.now()->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Order::query()->where('order_code', $code)->exists());

        return $code;
    }
}
