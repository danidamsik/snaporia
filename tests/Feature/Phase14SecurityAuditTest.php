<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class Phase14SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_routes_for_checkout_history_and_download_redirect_to_login(): void
    {
        [, $order, $photo] = $this->makePaidOrder();

        $this->get(route('checkout.single.show', ['photos' => [$photo->id]]))
            ->assertRedirect(route('login'));

        $this->get(route('visitor.orders.index'))
            ->assertRedirect(route('login'));

        $this->get(route('visitor.downloads.index'))
            ->assertRedirect(route('login'));

        $this->get(route('visitor.orders.photos.download', [$order, $photo]))
            ->assertRedirect(route('login'));
    }

    public function test_midtrans_notification_writes_audit_logs_without_sensitive_signature_data(): void
    {
        Log::spy();
        config(['midtrans.server_key' => 'test-server-key']);

        [, $order, , $transaction] = $this->makePaidOrder([
            'status' => Order::STATUS_PENDING,
            'paid_at' => null,
        ], [
            'status' => 'pending',
            'payload' => ['token' => 'snap-token-test'],
        ]);

        $payload = [
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '25000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-paid-42',
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = $this->signatureFor($payload);

        $this->postJson(route('payment.midtrans.notification'), $payload)
            ->assertOk();

        Log::shouldHaveReceived('info')
            ->with('Midtrans payment callback accepted.', Mockery::on(function (array $context) use ($transaction) {
                return $context['transaction_id'] === $transaction->id
                    && $context['order_id'] === $transaction->order_id
                    && $context['transaction_status'] === 'settlement'
                    && ! array_key_exists('signature_key', $context);
            }))
            ->once();

        Log::shouldHaveReceived('info')
            ->with('Transaction status changed.', Mockery::on(function (array $context) use ($order, $transaction) {
                return $context['source'] === 'midtrans_notification'
                    && $context['transaction_id'] === $transaction->id
                    && $context['order_id'] === $order->id
                    && $context['previous_transaction_status'] === 'pending'
                    && $context['transaction_status'] === 'settlement'
                    && $context['previous_order_status'] === Order::STATUS_PENDING
                    && $context['order_status'] === Order::STATUS_PAID;
            }))
            ->once();
    }

    public function test_missing_original_download_is_logged_without_internal_path_leak(): void
    {
        Storage::fake('local');
        Log::spy();

        [$visitor, $order, $photo] = $this->makePaidOrder();

        $this->actingAs($visitor)
            ->from(route('visitor.orders.show', $order))
            ->get(route('visitor.orders.photos.download', [$order, $photo]))
            ->assertRedirect(route('visitor.orders.show', $order))
            ->assertSessionHas('error', 'File original tidak ditemukan. Hubungi admin Snaporia.');

        Log::shouldHaveReceived('warning')
            ->with('Original photo download failed because file is missing.', Mockery::on(function (array $context) use ($order, $photo) {
                return $context['order_id'] === $order->id
                    && $context['photo_id'] === $photo->id
                    && ! array_key_exists('path', $context)
                    && ! array_key_exists('original_path', $context);
            }))
            ->once();
    }

    public function test_production_500_response_uses_generic_message(): void
    {
        config([
            'app.debug' => false,
        ]);

        Route::middleware('web')->get('/_phase14/error', function () {
            throw new \RuntimeException('SQLSTATE[HY000] storage/app/secrets.txt');
        });

        $this->get('/_phase14/error')
            ->assertStatus(500)
            ->assertSeeText('Terjadi kesalahan pada server. Silakan coba lagi beberapa saat.')
            ->assertDontSeeText('SQLSTATE')
            ->assertDontSeeText('storage/app/secrets.txt');
    }

    private function makePaidOrder(array $orderOverrides = [], array $transactionOverrides = []): array
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
        $photo = Photo::create([
            'event_id' => $event->id,
            'original_path' => "photos/original/{$event->id}/photo-1.jpg",
            'watermarked_path' => "photos/watermarked/{$event->id}/photo-1.jpg",
            'filename' => 'photo-1.jpg',
            'file_size' => 1001,
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ]);
        $order = Order::create(array_merge([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-TEST-0001',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PAID,
            'expires_at' => now()->addDay(),
            'paid_at' => now(),
        ], $orderOverrides));

        OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $photo->id,
            'price' => 25000,
        ]);

        $transaction = Transaction::create(array_merge([
            'order_id' => $order->id,
            'midtrans_order_id' => 'MT-'.$order->order_code,
            'snap_token' => 'snap-token-test',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
            'payment_type' => null,
            'gross_amount' => $order->total_amount,
            'status' => 'settlement',
            'fraud_status' => null,
            'expires_at' => $order->expires_at,
            'payload' => ['token' => 'snap-token-test'],
        ], $transactionOverrides));

        return [$visitor, $order, $photo, $transaction];
    }

    private function signatureFor(array $payload): string
    {
        return hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('midtrans.server_key')
        );
    }
}
