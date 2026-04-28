<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminEventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_only_owned_events(): void
    {
        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $ownedEvent = $this->makeEvent($admin, ['name' => 'Owned Event']);
        $this->makeEvent($otherAdmin, ['name' => 'Other Event']);

        $this->actingAs($admin)
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.id', $ownedEvent->id)
                ->where('events.data.0.name', 'Owned Event')
            );
    }

    public function test_admin_can_create_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.events.store'), $this->eventPayload([
                'name' => 'New Event',
                'is_published' => true,
            ]))
            ->assertRedirect(route('admin.events.index'));

        $this->assertDatabaseHas('events', [
            'admin_id' => $admin->id,
            'name' => 'New Event',
            'is_published' => true,
        ]);
    }

    public function test_admin_can_update_owned_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);

        $this->actingAs($admin)
            ->put(route('admin.events.update', $event), $this->eventPayload([
                'name' => 'Updated Event',
                'price_per_photo' => 30000,
                'price_package' => 150000,
                'is_published' => false,
            ]))
            ->assertRedirect(route('admin.events.index'));

        $event->refresh();

        $this->assertSame('Updated Event', $event->name);
        $this->assertSame('30000.00', $event->price_per_photo);
        $this->assertFalse($event->is_published);
    }

    public function test_admin_can_not_access_other_admin_event(): void
    {
        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $otherEvent = $this->makeEvent($otherAdmin);

        $this->actingAs($admin)
            ->get(route('admin.events.edit', $otherEvent))
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.events.update', $otherEvent), $this->eventPayload(['name' => 'Hacked']))
            ->assertForbidden();

        $this->assertNotSame('Hacked', $otherEvent->refresh()->name);
    }

    public function test_admin_can_publish_and_unpublish_owned_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['is_published' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.events.publish', $event))
            ->assertRedirect();

        $this->assertTrue($event->refresh()->is_published);

        $this->actingAs($admin)
            ->patch(route('admin.events.unpublish', $event))
            ->assertRedirect();

        $this->assertFalse($event->refresh()->is_published);
    }

    public function test_admin_can_delete_event_without_orders(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $this->makePhoto($event);

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', $event))
            ->assertRedirect();

        $this->assertNull($event->fresh());
        $this->assertDatabaseMissing('photos', [
            'event_id' => $event->id,
        ]);
    }

    public function test_admin_can_not_delete_event_with_orders(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $this->makeOrder($visitor, $event);

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('error', 'Event tidak dapat dihapus karena sudah memiliki order atau transaksi.');

        $this->assertNotNull($event->fresh());
    }

    public function test_visitor_can_not_access_admin_event_management(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($visitor)
            ->get(route('admin.events.index'))
            ->assertForbidden();
    }

    private function makeEvent(User $admin, array $overrides = []): Event
    {
        return Event::create(array_merge($this->eventPayload(), [
            'admin_id' => $admin->id,
        ], $overrides));
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Event Fixture',
            'description' => 'Fixture description',
            'date' => '2026-04-28',
            'location' => 'Makassar',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ], $overrides);
    }

    private function makeOrder(User $visitor, Event $event): Order
    {
        return Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-ADMIN-EVENT-0001',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
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
}
