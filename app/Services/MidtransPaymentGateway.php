<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Transaction;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;

class MidtransPaymentGateway implements PaymentGateway
{
    public function createTransaction(Order $order, string $midtransOrderId): array
    {
        $this->configure();

        $order->loadMissing(['event', 'items.photo', 'user']);

        $response = Snap::createTransaction([
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int) $order->total_amount,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'item_details' => $this->itemDetails($order),
            'callbacks' => [
                'finish' => route('checkout.orders.show', $order),
                'unfinish' => route('checkout.orders.show', $order),
                'error' => route('checkout.orders.show', $order),
            ],
        ]);

        return [
            'midtrans_order_id' => $midtransOrderId,
            'snap_token' => $response->token ?? null,
            'payment_url' => $response->redirect_url ?? null,
            'payment_type' => null,
            'gross_amount' => $order->total_amount,
            'status' => 'pending',
            'fraud_status' => null,
            'expires_at' => $order->expires_at,
            'payload' => json_decode(json_encode($response), true),
        ];
    }

    public function status(Transaction $transaction): array
    {
        $this->configure();

        return json_decode(json_encode(MidtransTransaction::status($transaction->midtrans_order_id)), true);
    }

    private function configure(): void
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    private function itemDetails(Order $order): array
    {
        if ($order->type === Order::TYPE_PACKAGE) {
            return [[
                'id' => 'PACKAGE-'.$order->event_id,
                'price' => (int) $order->total_amount,
                'quantity' => 1,
                'name' => mb_substr('Paket '.$order->event->name, 0, 50),
            ]];
        }

        return $order->items
            ->map(fn ($item) => [
                'id' => 'PHOTO-'.$item->photo_id,
                'price' => (int) $item->price,
                'quantity' => 1,
                'name' => mb_substr($item->photo?->filename ?? 'Foto '.$item->photo_id, 0, 50),
            ])
            ->values()
            ->all();
    }
}
