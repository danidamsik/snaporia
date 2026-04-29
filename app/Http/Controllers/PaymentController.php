<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\PaymentTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    public function pay(Request $request, Order $order, PaymentTransactionService $payments): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $order);

        if ($this->expireIfNeeded($order)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Order sudah expired. Silakan buat order baru.',
                    'order' => $this->orderPayload($order->refresh()),
                ], 422);
            }

            return redirect()
                ->route('checkout.orders.show', $order)
                ->with('warning', 'Order sudah expired. Silakan buat order baru.');
        }

        if ($order->status !== Order::STATUS_PENDING) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Order ini tidak lagi berstatus pending.',
                    'order' => $this->orderPayload($order),
                ], 422);
            }

            return redirect()
                ->route('checkout.orders.show', $order)
                ->with('info', 'Order ini tidak lagi berstatus pending.');
        }

        try {
            $result = $payments->createOrReuse($order);
        } catch (Throwable $exception) {
            Log::error('Midtrans payment creation failed.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Gagal membuat pembayaran Midtrans. Coba lagi beberapa saat.',
                ], 502);
            }

            return redirect()
                ->route('checkout.orders.show', $order)
                ->with('error', 'Gagal membuat pembayaran Midtrans. Coba lagi beberapa saat.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['created']
                    ? 'Pembayaran Midtrans berhasil dibuat.'
                    : 'Pembayaran Midtrans yang sudah tersedia digunakan kembali.',
                'payment' => $this->paymentPayload($result['transaction']),
                'order' => $this->orderPayload($order),
            ]);
        }

        return redirect()
            ->route('checkout.orders.show', $order)
            ->with($result['created'] ? 'success' : 'info', $result['created']
                ? 'Link pembayaran Midtrans berhasil dibuat.'
                : 'Link pembayaran Midtrans sudah tersedia.');
    }

    public function refresh(Request $request, Order $order, PaymentGateway $gateway): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $order);

        $transaction = $order->transactions()->latest()->first();
        if (! $transaction) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Pembayaran belum dibuat untuk order ini.',
                    'order' => $this->orderPayload($order),
                ], 422);
            }

            return redirect()
                ->route('checkout.orders.show', $order)
                ->with('warning', 'Pembayaran belum dibuat untuk order ini.');
        }

        try {
            $this->applyPaymentPayload($transaction, $gateway->status($transaction), 'status_refresh');
        } catch (Throwable $exception) {
            Log::warning('Midtrans status refresh failed.', [
                'order_id' => $order->id,
                'transaction_id' => $transaction->id,
                'message' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Status pembayaran belum bisa dicek. Coba lagi nanti.',
                ], 502);
            }

            return redirect()
                ->route('checkout.orders.show', $order)
                ->with('error', 'Status pembayaran belum bisa dicek. Coba lagi nanti.');
        }

        $transaction->refresh();
        $order->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Status pembayaran diperbarui.',
                'order' => $this->orderPayload($order),
                'payment' => $this->paymentPayload($transaction),
            ]);
        }

        return redirect()
            ->route('checkout.orders.show', $order)
            ->with('success', 'Status pembayaran diperbarui.');
    }

    public function notification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $midtransOrderId = (string) ($payload['order_id'] ?? '');

        $transaction = Transaction::query()
            ->with('order')
            ->where('midtrans_order_id', $midtransOrderId)
            ->first();

        if (! $transaction) {
            Log::warning('Midtrans notification ignored because transaction was not found.', [
                'midtrans_order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        if (! $this->hasValidSignature($payload)) {
            Log::warning('Midtrans notification rejected because signature is invalid.', [
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        if (! $this->amountMatches($transaction->order, $payload['gross_amount'] ?? null)) {
            Log::warning('Midtrans notification rejected because amount does not match.', [
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => $midtransOrderId,
            ]);

            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        Log::info('Midtrans payment callback accepted.', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'midtrans_order_id' => $midtransOrderId,
            'transaction_status' => $payload['transaction_status'] ?? null,
        ]);

        $this->applyPaymentPayload($transaction, $payload, 'midtrans_notification');

        return response()->json(['message' => 'OK']);
    }

    private function applyPaymentPayload(Transaction $transaction, array $payload, string $source): void
    {
        DB::transaction(function () use ($transaction, $payload, $source): void {
            $transaction = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($transaction->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->amountMatches($order, $payload['gross_amount'] ?? null)) {
                throw ValidationException::withMessages([
                    'gross_amount' => 'Nominal pembayaran tidak sesuai dengan total order.',
                ]);
            }

            $transactionStatus = (string) ($payload['transaction_status'] ?? $transaction->status);
            $mappedStatus = $this->mapOrderStatus($transactionStatus);
            $previousTransactionStatus = $transaction->status;
            $previousOrderStatus = $order->status;

            $transaction->update([
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $transaction->midtrans_transaction_id,
                'payment_type' => $payload['payment_type'] ?? $transaction->payment_type,
                'gross_amount' => $payload['gross_amount'] ?? $transaction->gross_amount,
                'status' => $transactionStatus,
                'fraud_status' => $payload['fraud_status'] ?? $transaction->fraud_status,
                'payload' => $payload,
            ]);

            $nextOrderStatus = $order->status;

            if ($order->status === Order::STATUS_PAID) {
                $nextOrderStatus = $order->status;
            } elseif ($mappedStatus === Order::STATUS_PAID) {
                $order->update([
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                ]);

                $nextOrderStatus = Order::STATUS_PAID;
            } elseif (in_array($mappedStatus, [Order::STATUS_FAILED, Order::STATUS_EXPIRED], true)) {
                $order->update([
                    'status' => $mappedStatus,
                    'paid_at' => null,
                ]);

                $nextOrderStatus = $mappedStatus;
            }

            if ($previousTransactionStatus !== $transactionStatus || $previousOrderStatus !== $nextOrderStatus) {
                Log::info('Transaction status changed.', [
                    'source' => $source,
                    'transaction_id' => $transaction->id,
                    'order_id' => $order->id,
                    'midtrans_order_id' => $transaction->midtrans_order_id,
                    'previous_transaction_status' => $previousTransactionStatus,
                    'transaction_status' => $transactionStatus,
                    'previous_order_status' => $previousOrderStatus,
                    'order_status' => $nextOrderStatus,
                ]);
            }
        });
    }

    private function mapOrderStatus(string $transactionStatus): string
    {
        return match ($transactionStatus) {
            'settlement', 'capture' => Order::STATUS_PAID,
            'failure', 'deny', 'cancel' => Order::STATUS_FAILED,
            'expire' => Order::STATUS_EXPIRED,
            default => Order::STATUS_PENDING,
        };
    }

    private function hasValidSignature(array $payload): bool
    {
        $signature = (string) ($payload['signature_key'] ?? '');
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $serverKey = (string) config('midtrans.server_key');

        if ($signature === '' || $orderId === '' || $statusCode === '' || $grossAmount === '' || $serverKey === '') {
            return false;
        }

        return hash_equals(hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey), $signature);
    }

    private function amountMatches(Order $order, mixed $grossAmount): bool
    {
        return (int) round((float) $grossAmount) === (int) round((float) $order->total_amount);
    }

    private function expireIfNeeded(Order $order): bool
    {
        if ($order->status === Order::STATUS_PENDING && $order->expires_at && $order->expires_at->isPast()) {
            $order->update(['status' => Order::STATUS_EXPIRED]);

            return true;
        }

        return false;
    }

    private function orderPayload(Order $order): array
    {
        return [
            'status' => $order->status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'url' => route('checkout.orders.show', $order),
        ];
    }

    private function paymentPayload(Transaction $transaction): array
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
}
