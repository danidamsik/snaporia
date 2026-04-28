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

class DashboardRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_shows_system_wide_summary(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);
        $order = $this->makeOrder($visitor, $event, [
            'status' => Order::STATUS_PAID,
            'total_amount' => 75000,
            'paid_at' => now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'photo_id' => $photo->id, 'price' => 75000]);
        $transaction = $this->makeTransaction($order, 'MT-SNP-DASH-SUPER-0001', ['status' => 'settlement']);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboardRole', User::ROLE_SUPER_ADMIN)
                ->where('stats.0.value', 3)
                ->where('stats.1.value', 1)
                ->where('stats.2.value', 1)
                ->where('stats.3.value', 1)
                ->where('stats.4.value', 1)
                ->where('stats.5.value', 1)
                ->where('stats.6.value', 75000)
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.id', $transaction->id)
                ->has('recentEvents', 1)
            );
    }

    public function test_admin_dashboard_only_uses_owned_operational_data(): void
    {
        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $ownedEvent = $this->makeEvent($admin, ['name' => 'Owned Event']);
        $otherEvent = $this->makeEvent($otherAdmin, ['name' => 'Other Event']);
        $ownedPhoto = $this->makePhoto($ownedEvent);
        $this->makePhoto($otherEvent);
        $ownedOrder = $this->makeOrder($visitor, $ownedEvent, [
            'status' => Order::STATUS_PAID,
            'total_amount' => 100000,
            'paid_at' => now(),
        ]);
        OrderItem::create(['order_id' => $ownedOrder->id, 'photo_id' => $ownedPhoto->id, 'price' => 100000]);
        $ownedTransaction = $this->makeTransaction($ownedOrder, 'MT-SNP-DASH-ADMIN-0001', ['status' => 'settlement']);
        $otherOrder = $this->makeOrder($visitor, $otherEvent, [
            'order_code' => 'SNP-DASH-OTHER-0001',
            'status' => Order::STATUS_PAID,
            'total_amount' => 999999,
            'paid_at' => now(),
        ]);
        $this->makeTransaction($otherOrder, 'MT-SNP-DASH-OTHER-0001', ['status' => 'settlement']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboardRole', User::ROLE_ADMIN)
                ->where('stats.0.value', 1)
                ->where('stats.1.value', 1)
                ->where('stats.2.value', 1)
                ->where('stats.3.value', 100000)
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.id', $ownedTransaction->id)
                ->has('recentEvents', 1)
                ->where('recentEvents.0.name', 'Owned Event')
            );
    }

    public function test_visitor_dashboard_only_shows_owned_orders(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$visitor, $otherVisitor] = User::factory()->count(2)->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);
        $paidOrder = $this->makeOrder($visitor, $event, [
            'order_code' => 'SNP-DASH-VISITOR-0001',
            'status' => Order::STATUS_PAID,
            'total_amount' => 50000,
            'paid_at' => now(),
        ]);
        OrderItem::create(['order_id' => $paidOrder->id, 'photo_id' => $photo->id, 'price' => 50000]);
        $this->makeOrder($visitor, $event, [
            'order_code' => 'SNP-DASH-PENDING-0001',
            'status' => Order::STATUS_PENDING,
            'total_amount' => 25000,
        ]);
        $this->makeOrder($otherVisitor, $event, [
            'order_code' => 'SNP-DASH-OTHER-0001',
            'status' => Order::STATUS_PAID,
            'total_amount' => 999999,
            'paid_at' => now(),
        ]);

        $this->actingAs($visitor)
            ->get(route('visitor.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboardRole', User::ROLE_VISITOR)
                ->where('stats.0.value', 2)
                ->where('stats.1.value', 1)
                ->where('stats.2.value', 1)
                ->where('stats.3.value', 50000)
                ->has('recentOrders', 2)
                ->where('recentOrders.0.order_code', 'SNP-DASH-PENDING-0001')
                ->where('recentOrders.1.order_code', 'SNP-DASH-VISITOR-0001')
            );
    }

    public function test_dashboard_shows_empty_state_data_when_no_operational_data_exists(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($visitor)
            ->get(route('visitor.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.0.value', 0)
                ->where('stats.1.value', 0)
                ->where('stats.2.value', 0)
                ->where('stats.3.value', 0)
                ->has('recentOrders', 0)
            );
    }

    private function makeEvent(User $admin, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'admin_id' => $admin->id,
            'name' => 'Dashboard Event',
            'description' => 'Fixture',
            'date' => '2026-04-28',
            'location' => 'Makassar',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ], $overrides));
    }

    private function makePhoto(Event $event): Photo
    {
        return Photo::create([
            'event_id' => $event->id,
            'original_path' => "photos/original/{$event->id}/photo.jpg",
            'watermarked_path' => "photos/watermarked/{$event->id}/photo.jpg",
            'filename' => 'photo.jpg',
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ]);
    }

    private function makeOrder(User $visitor, Event $event, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-DASH-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'paid_at' => null,
        ], $overrides));
    }

    private function makeTransaction(Order $order, string $midtransOrderId, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'order_id' => $order->id,
            'midtrans_order_id' => $midtransOrderId,
            'gross_amount' => $order->total_amount,
            'status' => 'pending',
            'expires_at' => $order->expires_at,
            'payload' => [],
        ], $overrides));
    }
}
