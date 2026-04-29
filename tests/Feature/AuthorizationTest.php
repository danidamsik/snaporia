<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_routes_are_limited_by_role(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($superAdmin)->get('/super-admin/dashboard')->assertOk();
        $this->actingAs($superAdmin)->get('/admin/dashboard')->assertForbidden();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $this->actingAs($visitor)->get('/dashboard')->assertRedirect(route('events.index'));
        $this->actingAs($visitor)->get('/super-admin/dashboard')->assertForbidden();
    }

    public function test_dashboard_redirects_to_the_authenticated_users_role_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($visitor)
            ->get('/dashboard')
            ->assertRedirect(route('events.index'));
    }

    public function test_inactive_authenticated_user_gets_forbidden_on_protected_routes(): void
    {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($inactiveUser)->get('/dashboard')->assertForbidden();
    }

    public function test_admin_can_only_authorize_owned_events_and_photos(): void
    {
        [$owner, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($owner);
        $otherEvent = $this->makeEvent($otherAdmin);
        $photo = $this->makePhoto($event);
        $otherPhoto = $this->makePhoto($otherEvent);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $event));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $otherEvent));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $photo));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $otherPhoto));
    }

    public function test_visitor_can_only_authorize_owned_orders(): void
    {
        [$visitor, $otherVisitor] = User::factory()->count(2)->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $order = $this->makeOrder($visitor, $event);
        $otherOrder = $this->makeOrder($otherVisitor, $event, 'SNP-TEST-0002');

        $this->assertTrue(Gate::forUser($visitor)->allows('view', $order));
        $this->assertFalse(Gate::forUser($visitor)->allows('view', $otherOrder));
    }

    public function test_super_admin_can_only_delete_users_without_operational_data(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $adminWithoutData = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $adminWithData = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->makeEvent($adminWithData);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $adminWithoutData));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('delete', $adminWithData));
    }

    public function test_admin_can_only_view_transactions_for_owned_events(): void
    {
        [$owner, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $transaction = $this->makeTransaction($this->makeOrder($visitor, $this->makeEvent($owner)));
        $otherTransaction = $this->makeTransaction(
            $this->makeOrder($visitor, $this->makeEvent($otherAdmin), 'SNP-TEST-0003'),
            'MT-SNP-TEST-0003'
        );

        $this->assertTrue(Gate::forUser($owner)->allows('view', $transaction));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $otherTransaction));
    }

    private function makeEvent(User $admin): Event
    {
        return Event::create([
            'admin_id' => $admin->id,
            'name' => 'Test Event',
            'description' => 'Authorization fixture',
            'date' => '2026-04-28',
            'location' => 'Makassar',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ]);
    }

    private function makePhoto(Event $event): Photo
    {
        return Photo::create([
            'event_id' => $event->id,
            'original_path' => "photos/original/{$event->id}/test.jpg",
            'watermarked_path' => "photos/watermarked/{$event->id}/test.jpg",
            'filename' => 'test.jpg',
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ]);
    }

    private function makeOrder(User $visitor, Event $event, string $orderCode = 'SNP-TEST-0001'): Order
    {
        return Order::create([
            'user_id' => $visitor->id,
            'order_code' => $orderCode,
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function makeTransaction(Order $order, string $midtransOrderId = 'MT-SNP-TEST-0001'): Transaction
    {
        return Transaction::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $midtransOrderId,
            'gross_amount' => $order->total_amount,
            'status' => 'pending',
            'expires_at' => $order->expires_at,
            'payload' => [],
        ]);
    }
}
