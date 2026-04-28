<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_before_checkout(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);

        $this->get(route('checkout.single.show', ['photos' => [$photo->id]]))
            ->assertRedirect(route('login'));
    }

    public function test_visitor_can_view_single_checkout_summary(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['price_per_photo' => 25000]);
        $photo = $this->makePhoto($event, ['filename' => 'wisuda-001.jpg']);

        $this->actingAs($visitor)
            ->get(route('checkout.single.show', ['photos' => [$photo->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Show')
                ->where('checkout.mode', 'preview')
                ->where('checkout.type', Order::TYPE_SINGLE)
                ->where('checkout.event.id', $event->id)
                ->where('checkout.photos_count', 1)
                ->where('checkout.total_amount', 25000)
            );
    }

    public function test_single_checkout_creates_pending_order_with_items_and_24_hour_expiry(): void
    {
        $this->travelTo('2026-04-29 10:00:00');

        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['price_per_photo' => 25000]);
        $firstPhoto = $this->makePhoto($event, ['filename' => 'wisuda-001.jpg']);
        $secondPhoto = $this->makePhoto($event, ['filename' => 'wisuda-002.jpg', 'sort_order' => 2]);

        $response = $this->actingAs($visitor)
            ->post(route('checkout.single.store'), [
                'photos' => [$firstPhoto->id, $secondPhoto->id],
            ]);

        $order = Order::query()->first();

        $response->assertRedirect(route('checkout.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $visitor->id,
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 50000,
            'status' => Order::STATUS_PENDING,
        ]);
        $this->assertSame('2026-04-30 10:00:00', $order->expires_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'photo_id' => $firstPhoto->id,
            'price' => 25000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'photo_id' => $secondPhoto->id,
            'price' => 25000,
        ]);
    }

    public function test_package_checkout_creates_pending_order_for_all_event_photos(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['price_package' => 175000]);
        $firstPhoto = $this->makePhoto($event);
        $secondPhoto = $this->makePhoto($event, ['filename' => 'wisuda-002.jpg', 'sort_order' => 2]);

        $response = $this->actingAs($visitor)
            ->post(route('checkout.package.store', $event));

        $order = Order::query()->first();

        $response->assertRedirect(route('checkout.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'type' => Order::TYPE_PACKAGE,
            'event_id' => $event->id,
            'total_amount' => 175000,
            'status' => Order::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'photo_id' => $firstPhoto->id,
            'price' => 0,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'photo_id' => $secondPhoto->id,
            'price' => 0,
        ]);
    }

    public function test_single_checkout_rejects_photos_from_different_events(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $firstEvent = $this->makeEvent($admin, ['name' => 'First Event']);
        $secondEvent = $this->makeEvent($admin, ['name' => 'Second Event']);
        $firstPhoto = $this->makePhoto($firstEvent);
        $secondPhoto = $this->makePhoto($secondEvent);

        $this->actingAs($visitor)
            ->from(route('events.show', $firstEvent))
            ->post(route('checkout.single.store'), [
                'photos' => [$firstPhoto->id, $secondPhoto->id],
            ])
            ->assertRedirect(route('events.show', $firstEvent))
            ->assertSessionHasErrors('photos');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_unpublished_events_and_empty_package_events(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $draftEvent = $this->makeEvent($admin, ['is_published' => false]);
        $draftPhoto = $this->makePhoto($draftEvent);
        $emptyEvent = $this->makeEvent($admin, ['name' => 'Empty Event']);

        $this->actingAs($visitor)
            ->post(route('checkout.single.store'), [
                'photos' => [$draftPhoto->id],
            ])
            ->assertSessionHasErrors('photos');

        $this->actingAs($visitor)
            ->post(route('checkout.package.store', $emptyEvent))
            ->assertSessionHasErrors('event');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_duplicate_paid_single_or_package_purchase(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);
        $paidOrder = $this->makePaidOrder($visitor, $event, Order::TYPE_SINGLE);
        OrderItem::create([
            'order_id' => $paidOrder->id,
            'photo_id' => $photo->id,
            'price' => 25000,
        ]);

        $this->actingAs($visitor)
            ->post(route('checkout.single.store'), [
                'photos' => [$photo->id],
            ])
            ->assertRedirect(route('checkout.orders.show', $paidOrder))
            ->assertSessionHas('warning');

        $paidPackage = $this->makePaidOrder($visitor, $event, Order::TYPE_PACKAGE, 'SNP-PAID-PACKAGE');

        $this->actingAs($visitor)
            ->post(route('checkout.package.store', $event))
            ->assertRedirect(route('checkout.orders.show', $paidPackage))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('orders', 2);
    }

    private function makeEvent(User $admin, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'admin_id' => $admin->id,
            'name' => 'Public Event',
            'description' => 'Fixture description',
            'date' => '2026-04-28',
            'location' => 'Makassar',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ], $overrides));
    }

    private function makePhoto(Event $event, array $overrides = []): Photo
    {
        return Photo::create(array_merge([
            'event_id' => $event->id,
            'original_path' => "photos/original/{$event->id}/original.jpg",
            'watermarked_path' => "photos/watermarked/{$event->id}/watermarked.jpg",
            'filename' => 'photo.jpg',
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ], $overrides));
    }

    private function makePaidOrder(User $visitor, Event $event, string $type, string $code = 'SNP-PAID-SINGLE'): Order
    {
        return Order::create([
            'user_id' => $visitor->id,
            'order_code' => $code,
            'type' => $type,
            'event_id' => $event->id,
            'total_amount' => $type === Order::TYPE_PACKAGE ? $event->price_package : $event->price_per_photo,
            'status' => Order::STATUS_PAID,
            'expires_at' => now()->addDay(),
            'paid_at' => now(),
        ]);
    }
}
