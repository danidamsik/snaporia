<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionMonitoringReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_only_sees_transactions_for_owned_events(): void
    {
        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $ownedTransaction = $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($admin), [
            'order_code' => 'SNP-OWNED-0001',
        ]));
        $otherTransaction = $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($otherAdmin), [
            'order_code' => 'SNP-OTHER-0001',
        ]), 'MT-SNP-OTHER-0001');

        $this->actingAs($admin)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $ownedTransaction->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $otherTransaction))
            ->assertForbidden();
    }

    public function test_super_admin_can_filter_transactions_by_admin_status_and_keyword(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $matchedTransaction = $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($admin), [
            'order_code' => 'SNP-MATCH-0001',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]), 'MT-SNP-MATCH-0001', ['status' => 'settlement']);
        $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($otherAdmin), [
            'order_code' => 'SNP-OTHER-0001',
            'status' => Order::STATUS_PENDING,
        ]), 'MT-SNP-OTHER-0001');

        $this->actingAs($superAdmin)
            ->get(route('super-admin.transactions.index', [
                'admin_id' => $admin->id,
                'status' => Order::STATUS_PAID,
                'q' => 'MATCH',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $matchedTransaction->id)
                ->where('transactions.data.0.admin.id', $admin->id)
            );
    }

    public function test_transaction_detail_contains_order_user_event_items_and_payment_data(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $order = $this->makeOrder($visitor, $this->makeEvent($admin), [
            'order_code' => 'SNP-DETAIL-0001',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ], itemCount: 2);
        $transaction = $this->makeTransaction($order, 'MT-SNP-DETAIL-0001', [
            'status' => 'settlement',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Show')
                ->where('transaction.id', $transaction->id)
                ->where('transaction.order.order_code', 'SNP-DETAIL-0001')
                ->where('transaction.user.id', $visitor->id)
                ->where('transaction.admin.id', $admin->id)
                ->where('transaction.event.id', $order->event_id)
                ->where('transaction.payment_type', 'qris')
                ->has('transaction.items', 2)
            );
    }

    public function test_super_admin_can_delete_failed_transaction_but_not_paid_transaction(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $failedTransaction = $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($admin), [
            'order_code' => 'SNP-FAILED-0001',
            'status' => Order::STATUS_FAILED,
        ]), 'MT-SNP-FAILED-0001', ['status' => 'failure']);
        $paidTransaction = $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($admin), [
            'order_code' => 'SNP-PAID-0001',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]), 'MT-SNP-PAID-0001', ['status' => 'settlement']);

        $this->actingAs($superAdmin)
            ->delete(route('super-admin.transactions.destroy', $failedTransaction))
            ->assertRedirect(route('super-admin.transactions.index'));

        $this->assertNull($failedTransaction->fresh());

        $this->actingAs($superAdmin)
            ->delete(route('super-admin.transactions.destroy', $paidTransaction))
            ->assertForbidden();

        $this->assertNotNull($paidTransaction->fresh());
    }

    public function test_admin_sales_report_uses_order_total_amount_for_revenue(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);

        $singleOrder = $this->makeOrder($visitor, $event, [
            'order_code' => 'SNP-SINGLE-0001',
            'type' => Order::TYPE_SINGLE,
            'total_amount' => 25000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now()->subDay(),
        ]);
        $packageOrder = $this->makeOrder($visitor, $event, [
            'order_code' => 'SNP-PACKAGE-0001',
            'type' => Order::TYPE_PACKAGE,
            'total_amount' => 100000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ], itemCount: 3, itemPrice: 0);
        $this->makeOrder($visitor, $event, [
            'order_code' => 'SNP-PENDING-0001',
            'total_amount' => 50000,
            'status' => Order::STATUS_PENDING,
        ]);
        $this->makeOrder($visitor, $this->makeEvent($otherAdmin), [
            'order_code' => 'SNP-OTHER-0001',
            'total_amount' => 999999,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Sales')
                ->where('summary.total_revenue', 125000)
                ->where('summary.paid_orders_count', 2)
                ->where('summary.photos_sold_count', 4)
                ->has('orders.data', 2)
                ->where('orders.data.0.id', $packageOrder->id)
                ->where('orders.data.1.id', $singleOrder->id)
            );
    }

    private function makeEvent(User $admin): Event
    {
        return Event::create([
            'admin_id' => $admin->id,
            'name' => 'Test Event '.$admin->id.' '.Event::query()->count(),
            'description' => 'Fixture',
            'date' => '2026-04-28',
            'location' => 'Makassar',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ]);
    }

    private function makeOrder(
        User $visitor,
        Event $event,
        array $overrides = [],
        int $itemCount = 1,
        int $itemPrice = 25000,
    ): Order {
        $order = Order::create(array_merge([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-TEST-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => $itemPrice * $itemCount,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'paid_at' => null,
        ], $overrides));

        foreach (range(1, $itemCount) as $index) {
            $photo = Photo::create([
                'event_id' => $event->id,
                'original_path' => "photos/original/{$event->id}/photo-{$index}-{$order->id}.jpg",
                'watermarked_path' => "photos/watermarked/{$event->id}/photo-{$index}-{$order->id}.jpg",
                'filename' => "photo-{$index}.jpg",
                'file_size' => 1000 + $index,
                'mime_type' => 'image/jpeg',
                'sort_order' => $index,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'photo_id' => $photo->id,
                'price' => $itemPrice,
            ]);
        }

        return $order;
    }

    private function makeTransaction(Order $order, string $midtransOrderId = 'MT-SNP-TEST-0001', array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'order_id' => $order->id,
            'midtrans_order_id' => $midtransOrderId,
            'midtrans_transaction_id' => null,
            'snap_token' => 'snap-token-test',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
            'payment_type' => null,
            'gross_amount' => $order->total_amount,
            'status' => 'pending',
            'fraud_status' => null,
            'expires_at' => $order->expires_at,
            'payload' => ['transaction_status' => 'pending'],
        ], $overrides));
    }
}
