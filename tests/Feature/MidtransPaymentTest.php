<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_create_midtrans_payment_for_pending_order(): void
    {
        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        [$visitor, $order] = $this->makePendingOrder();

        $this->actingAs($visitor)
            ->post(route('payment.orders.pay', $order))
            ->assertRedirect(route('checkout.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('transactions', [
            'order_id' => $order->id,
            'snap_token' => 'snap-token-test',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
            'gross_amount' => 50000,
            'status' => 'pending',
        ]);
        $this->assertStringStartsWith('MT-'.$order->order_code.'-'.$order->id.'-', Transaction::query()->firstOrFail()->midtrans_order_id);
    }

    public function test_existing_payment_link_is_reused(): void
    {
        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        [$visitor, $order] = $this->makePendingOrder();
        $this->makeTransaction($order);

        $this->actingAs($visitor)
            ->post(route('payment.orders.pay', $order))
            ->assertRedirect(route('checkout.orders.show', $order))
            ->assertSessionHas('info');

        $this->assertSame(0, $gateway->createCalls);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_refresh_payment_status_marks_order_paid_from_gateway_status(): void
    {
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway([
            'order_id' => 'MT-SNP-TEST-0001',
            'transaction_id' => 'trx-settlement-1',
            'transaction_status' => 'settlement',
            'gross_amount' => '50000.00',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ]));

        [$visitor, $order] = $this->makePendingOrder();
        $transaction = $this->makeTransaction($order);

        $this->actingAs($visitor)
            ->post(route('payment.orders.refresh', $order))
            ->assertRedirect(route('checkout.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('settlement', $transaction->refresh()->status);
        $this->assertSame('qris', $transaction->payment_type);
    }

    public function test_midtrans_notification_updates_order_and_is_idempotent(): void
    {
        config(['midtrans.server_key' => 'test-server-key']);

        [, $order] = $this->makePendingOrder();
        $transaction = $this->makeTransaction($order);
        $payload = $this->notificationPayload($transaction, [
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-paid-1',
        ]);

        $this->postJson(route('payment.midtrans.notification'), $payload)->assertOk();
        $this->postJson(route('payment.midtrans.notification'), $payload)->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('settlement', $transaction->refresh()->status);
        $this->assertSame('trx-paid-1', $transaction->midtrans_transaction_id);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_midtrans_notification_rejects_invalid_signature_and_amount(): void
    {
        config(['midtrans.server_key' => 'test-server-key']);

        [, $order] = $this->makePendingOrder();
        $transaction = $this->makeTransaction($order);

        $invalidSignaturePayload = $this->notificationPayload($transaction, [
            'signature_key' => 'bad-signature',
        ]);

        $this->postJson(route('payment.midtrans.notification'), $invalidSignaturePayload)
            ->assertForbidden();

        $invalidAmountPayload = $this->notificationPayload($transaction, [
            'gross_amount' => '99999.00',
        ]);
        $invalidAmountPayload['signature_key'] = $this->signatureFor($invalidAmountPayload);

        $this->postJson(route('payment.midtrans.notification'), $invalidAmountPayload)
            ->assertStatus(422);

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
        $this->assertSame('pending', $transaction->refresh()->status);
    }

    private function makePendingOrder(): array
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = Event::create([
            'admin_id' => $admin->id,
            'name' => 'Wisuda Nusantara',
            'description' => 'Fixture',
            'date' => '2026-04-28',
            'location' => 'Jakarta',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ]);
        $firstPhoto = $this->makePhoto($event, 'wisuda-001.jpg');
        $secondPhoto = $this->makePhoto($event, 'wisuda-002.jpg', 2);
        $order = Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-TEST-0001',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 50000,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $firstPhoto->id,
            'price' => 25000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $secondPhoto->id,
            'price' => 25000,
        ]);

        return [$visitor, $order];
    }

    private function makePhoto(Event $event, string $filename, int $sortOrder = 1): Photo
    {
        return Photo::create([
            'event_id' => $event->id,
            'original_path' => "photos/original/{$event->id}/{$filename}",
            'watermarked_path' => "photos/watermarked/{$event->id}/{$filename}",
            'filename' => $filename,
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'sort_order' => $sortOrder,
        ]);
    }

    private function makeTransaction(Order $order): Transaction
    {
        return Transaction::create([
            'order_id' => $order->id,
            'midtrans_order_id' => 'MT-'.$order->order_code,
            'snap_token' => 'snap-token-test',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
            'payment_type' => null,
            'gross_amount' => $order->total_amount,
            'status' => 'pending',
            'fraud_status' => null,
            'expires_at' => $order->expires_at,
            'payload' => ['token' => 'snap-token-test'],
        ]);
    }

    private function notificationPayload(Transaction $transaction, array $overrides = []): array
    {
        $payload = array_merge([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-test-1',
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
        ], $overrides);

        $payload['signature_key'] ??= $this->signatureFor($payload);

        return $payload;
    }

    private function signatureFor(array $payload): string
    {
        return hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('midtrans.server_key')
        );
    }
}

class FakePaymentGateway implements PaymentGateway
{
    public int $createCalls = 0;

    public function __construct(private readonly array $statusPayload = []) {}

    public function createTransaction(Order $order, string $midtransOrderId): array
    {
        $this->createCalls++;

        return [
            'midtrans_order_id' => $midtransOrderId,
            'snap_token' => 'snap-token-test',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
            'payment_type' => null,
            'gross_amount' => $order->total_amount,
            'status' => 'pending',
            'fraud_status' => null,
            'expires_at' => $order->expires_at,
            'payload' => ['token' => 'snap-token-test'],
        ];
    }

    public function status(Transaction $transaction): array
    {
        return $this->statusPayload;
    }
}
