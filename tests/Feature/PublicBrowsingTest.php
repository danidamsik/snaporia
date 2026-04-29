<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_index_only_shows_published_events(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $published = $this->makeEvent($admin, ['name' => 'Published Event', 'is_published' => true]);
        $draft = $this->makeEvent($admin, ['name' => 'Draft Event', 'is_published' => false]);
        $this->makePhoto($published);
        $this->makePhoto($draft, ['filename' => 'draft-secret.jpg']);

        $response = $this->get('/events');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.name', 'Published Event')
            );

        $this->assertStringNotContainsString('Draft Event', $response->getContent());
        $this->assertStringNotContainsString('original_path', $response->getContent());
    }

    public function test_public_event_search_matches_event_location_date_and_photo_filename(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, [
            'name' => 'Wisuda Nusantara',
            'date' => '2026-03-14',
            'location' => 'Balai Kartini',
        ]);
        $this->makePhoto($event, ['filename' => 'keluarga-rani.jpg']);

        $this->get('/events?q=keluarga-rani')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.name', 'Wisuda Nusantara')
            );

        $this->get('/events?date=2026-03-14&location=Kartini')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.location', 'Balai Kartini')
            );
    }

    public function test_unpublished_event_detail_returns_not_found(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['is_published' => false]);

        $this->get(route('events.show', $event))->assertNotFound();
    }

    public function test_event_detail_shows_watermarked_gallery_without_original_paths(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $this->makePhoto($event, [
            'filename' => 'wisuda-001.jpg',
            'original_path' => 'photos/original/1/private-original.jpg',
            'watermarked_path' => 'photos/watermarked/1/public-preview.jpg',
        ]);

        $response = $this->get(route('events.show', $event));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Events/Show')
                ->where('event.name', $event->name)
                ->has('photos.data', 1)
                ->where('photos.data.0.filename', 'wisuda-001.jpg')
            );

        $this->assertStringContainsString('watermarked', $response->getContent());
        $this->assertStringNotContainsString('private-original.jpg', $response->getContent());
        $this->assertStringNotContainsString('original_path', $response->getContent());
    }

    public function test_event_detail_marks_paid_photos_as_purchased_for_visitor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $purchasedPhoto = $this->makePhoto($event, ['filename' => 'purchased.jpg']);
        $availablePhoto = $this->makePhoto($event, ['filename' => 'available.jpg', 'sort_order' => 2]);
        $order = Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-PUBLIC-0001',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $purchasedPhoto->id,
            'price' => 25000,
        ]);

        $this->actingAs($visitor)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('photos.data.0.filename', 'purchased.jpg')
                ->where('photos.data.0.is_purchased', true)
                ->where('photos.data.1.filename', 'available.jpg')
                ->where('photos.data.1.is_purchased', false)
                ->where('event.is_package_purchased', false)
            );
    }

    public function test_event_detail_marks_package_as_purchased_when_package_or_all_photos_are_paid(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $firstPhoto = $this->makePhoto($event, ['filename' => 'first.jpg']);
        $secondPhoto = $this->makePhoto($event, ['filename' => 'second.jpg', 'sort_order' => 2]);
        $singleOrder = Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-PUBLIC-0002',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 50000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $singleOrder->id,
            'photo_id' => $firstPhoto->id,
            'price' => 25000,
        ]);
        OrderItem::create([
            'order_id' => $singleOrder->id,
            'photo_id' => $secondPhoto->id,
            'price' => 25000,
        ]);

        $this->actingAs($visitor)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.is_package_purchased', true)
            );

        $packageVisitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        Order::create([
            'user_id' => $packageVisitor->id,
            'order_code' => 'SNP-PUBLIC-0003',
            'type' => Order::TYPE_PACKAGE,
            'event_id' => $event->id,
            'total_amount' => 100000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($packageVisitor)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.is_package_purchased', true)
            );
    }

    public function test_gallery_search_only_uses_published_event_photos(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $published = $this->makeEvent($admin, ['name' => 'Seminar Digital', 'is_published' => true]);
        $draft = $this->makeEvent($admin, ['name' => 'Private Wedding', 'is_published' => false]);
        $this->makePhoto($published, ['filename' => 'seminar-hero.jpg']);
        $this->makePhoto($draft, ['filename' => 'private-wedding.jpg']);

        $response = $this->get('/gallery?q=seminar');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Gallery/Index')
                ->has('photos.data', 1)
                ->where('photos.data.0.filename', 'seminar-hero.jpg')
            );

        $this->assertStringNotContainsString('private-wedding.jpg', $response->getContent());
            $this->assertStringNotContainsString('original_path', $response->getContent());
    }

    public function test_public_gallery_per_page_uses_super_admin_setting(): void
    {
        Setting::create([
            'key' => 'public_gallery_per_page',
            'value' => '6',
            'description' => 'Fixture setting',
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);

        for ($index = 1; $index <= 7; $index++) {
            $this->makePhoto($event, [
                'filename' => "photo-{$index}.jpg",
                'sort_order' => $index,
            ]);
        }

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('photos.per_page', 6)
                ->has('photos.data', 6)
            );
    }

    public function test_public_watermarked_photo_route_streams_only_published_event_previews(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $published = $this->makeEvent($admin, ['is_published' => true]);
        $draft = $this->makeEvent($admin, ['is_published' => false]);
        $publishedPhoto = $this->makePhoto($published, ['watermarked_path' => 'photos/watermarked/published.jpg']);
        $draftPhoto = $this->makePhoto($draft, ['watermarked_path' => 'photos/watermarked/draft.jpg']);

        Storage::disk('local')->put($publishedPhoto->watermarked_path, 'preview');
        Storage::disk('local')->put($draftPhoto->watermarked_path, 'draft');

        $this->get(route('public.photos.watermarked', $publishedPhoto))->assertOk();
        $this->get(route('public.photos.watermarked', $draftPhoto))->assertNotFound();
    }

    public function test_public_photo_download_returns_watermark_until_photo_is_paid(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event, [
            'filename' => 'festival-001.png',
            'original_path' => 'photos/original/festival-001.png',
            'watermarked_path' => 'photos/watermarked/festival-001.jpg',
        ]);

        Storage::disk('local')->put($photo->original_path, 'original-content');
        Storage::disk('local')->put($photo->watermarked_path, 'watermark-content');

        $this->get(route('public.photos.preview', $photo))->assertOk();

        $this->get(route('public.photos.download', $photo))
            ->assertOk()
            ->assertDownload('festival-001-watermark.jpg');

        $order = Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-PUBLIC-0004',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $photo->id,
            'price' => 25000,
        ]);

        $this->actingAs($visitor)
            ->get(route('public.photos.preview', $photo))
            ->assertOk();

        $this->actingAs($visitor)
            ->get(route('public.photos.download', $photo))
            ->assertOk()
            ->assertDownload('festival-001.png');
    }

    private function makeEvent(User $admin, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'admin_id' => $admin->id,
            'name' => 'Public Event',
            'description' => 'Event fixture',
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
}
