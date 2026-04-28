<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Transaction;

interface PaymentGateway
{
    public function createTransaction(Order $order, string $midtransOrderId): array;

    public function status(Transaction $transaction): array;
}
