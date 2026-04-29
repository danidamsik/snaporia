<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitorOrderDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_view_only_owned_order_history(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $otherVisitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [, $ownedOrder] = $this->makeOrder($visitor, ['order_code' => 'SNP-OWNED-0001']);
        $this->makeOrder($otherVisitor, ['order_code' => 'SNP-OTHER-0001']);

        $this->actingAs($visitor)
            ->get(route('visitor.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Visitor/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $ownedOrder->id)
                ->where('orders.data.0.order_code', 'SNP-OWNED-0001')
            );
    }

    public function test_visitor_can_view_owned_order_detail_with_paginated_items(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [$event, $order] = $this->makeOrder($visitor, [
            'order_code' => 'SNP-PACKAGE-0001',
            'type' => Order::TYPE_PACKAGE,
            'total_amount' => 100000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ], 25);

        $this->actingAs($visitor)
            ->get(route('visitor.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Visitor/Orders/Show')
                ->where('order.id', $order->id)
                ->where('order.event.id', $event->id)
                ->where('items.total', 25)
                ->has('items.data', 20)
                ->where('items.data.0.download_url', route('visitor.orders.photos.download', [$order, $order->items()->first()->photo]))
            );
    }

    public function test_visitor_can_download_original_file_from_paid_order(): void
    {
        Storage::fake('local');

        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [, $order, $photo] = $this->makeOrder($visitor, [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        Storage::disk('local')->put($photo->original_path, 'original-content');

        $response = $this->actingAs($visitor)
            ->get(route('visitor.orders.photos.download', [$order, $photo]));

        $response->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $this->assertStringContainsString($order->order_code, $response->headers->get('content-disposition'));
    }

    public function test_download_is_rejected_for_unpaid_or_unowned_orders(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $otherVisitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [, $pendingOrder, $pendingPhoto] = $this->makeOrder($visitor, [
            'status' => Order::STATUS_PENDING,
        ]);
        [, $otherOrder, $otherPhoto] = $this->makeOrder($otherVisitor, [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($visitor)
            ->from(route('visitor.orders.show', $pendingOrder))
            ->get(route('visitor.orders.photos.download', [$pendingOrder, $pendingPhoto]))
            ->assertRedirect(route('visitor.orders.show', $pendingOrder))
            ->assertSessionHas('error', 'Download hanya tersedia untuk order paid.');

        $this->actingAs($visitor)
            ->get(route('visitor.orders.show', $otherOrder))
            ->assertForbidden();

        $this->actingAs($visitor)
            ->get(route('visitor.orders.photos.download', [$otherOrder, $otherPhoto]))
            ->assertForbidden();
    }

    public function test_pending_order_detail_exposes_payment_actions(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [, $order] = $this->makeOrder($visitor, [
            'status' => Order::STATUS_PENDING,
        ]);

        $this->actingAs($visitor)
            ->get(route('visitor.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Visitor/Orders/Show')
                ->where('order.status', Order::STATUS_PENDING)
                ->where('order.pay_url', route('payment.orders.pay', $order))
                ->where('order.refresh_url', route('payment.orders.refresh', $order))
                ->where('order.payment', null)
            );
    }

    public function test_missing_original_file_returns_safe_error(): void
    {
        Storage::fake('local');

        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [, $order, $photo] = $this->makeOrder($visitor, [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($visitor)
            ->from(route('visitor.orders.show', $order))
            ->get(route('visitor.orders.photos.download', [$order, $photo]))
            ->assertRedirect(route('visitor.orders.show', $order))
            ->assertSessionHas('error', 'File original tidak ditemukan. Hubungi admin Snaporia.');
    }

    public function test_downloads_page_only_lists_paid_orders(): void
    {
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        [, $paidOrder] = $this->makeOrder($visitor, [
            'order_code' => 'SNP-PAID-0001',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
        $this->makeOrder($visitor, [
            'order_code' => 'SNP-PENDING-0001',
            'status' => Order::STATUS_PENDING,
        ]);

        $this->actingAs($visitor)
            ->get(route('visitor.downloads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Visitor/Orders/Index')
                ->where('title', 'Download Saya')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $paidOrder->id)
            );
    }

    private function makeOrder(User $visitor, array $orderOverrides = [], int $itemCount = 1): array
    {
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
        $order = Order::create(array_merge([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-TEST-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000 * $itemCount,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'paid_at' => null,
        ], $orderOverrides));

        $firstPhoto = null;

        foreach (range(1, $itemCount) as $index) {
            $photo = Photo::create([
                'event_id' => $event->id,
                'original_path' => "photos/original/{$event->id}/photo-{$index}.jpg",
                'watermarked_path' => "photos/watermarked/{$event->id}/photo-{$index}.jpg",
                'filename' => "photo-{$index}.jpg",
                'file_size' => 1000 + $index,
                'mime_type' => 'image/jpeg',
                'sort_order' => $index,
            ]);

            $firstPhoto ??= $photo;

            OrderItem::create([
                'order_id' => $order->id,
                'photo_id' => $photo->id,
                'price' => $order->type === Order::TYPE_PACKAGE ? 0 : 25000,
            ]);
        }

        return [$event, $order, $firstPhoto];
    }
}
