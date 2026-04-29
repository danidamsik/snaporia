<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Str;

class PaymentTransactionService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function createOrReuse(Order $order): array
    {
        $existingTransaction = $order->transactions()->latest()->first();
        if ($existingTransaction?->snap_token || $existingTransaction?->payment_url) {
            return [
                'transaction' => $existingTransaction,
                'created' => false,
            ];
        }

        $payment = $this->gateway->createTransaction($order, $this->midtransOrderId($order));
        $transaction = Transaction::create($payment + [
            'order_id' => $order->id,
        ]);

        return [
            'transaction' => $transaction,
            'created' => true,
        ];
    }

    private function midtransOrderId(Order $order): string
    {
        return 'MT-'.$order->order_code.'-'.$order->id.'-'.Str::upper(Str::random(6));
    }
}
