<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\User;
use App\Services\WatermarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AdminPhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_only_owned_photos(): void
    {
        Storage::fake('local');

        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['name' => 'Owned Event']);
        $otherEvent = $this->makeEvent($otherAdmin, ['name' => 'Other Event']);
        $photo = $this->makePhoto($event, ['filename' => 'owned.jpg']);
        $this->makePhoto($otherEvent, ['filename' => 'other.jpg']);
        Storage::disk('local')->put($photo->watermarked_path, 'preview');

        $this->actingAs($admin)
            ->get(route('admin.photos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Photos/Index')
                ->has('photos.data', 1)
                ->where('photos.data.0.id', $photo->id)
                ->where('photos.data.0.filename', 'owned.jpg')
                ->where('photos.data.0.event_name', 'Owned Event')
                ->missing('photos.data.0.original_path')
            );
    }

    public function test_admin_can_open_upload_page_with_only_owned_events(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['name' => 'Owned Event']);
        $this->makeEvent($otherAdmin, ['name' => 'Other Event']);

        $this->actingAs($admin)
            ->get(route('admin.photos.upload'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Photos/Upload')
                ->has('events', 1)
                ->where('events.0.id', $event->id)
                ->where('limits.max_file_size_mb', 15)
                ->where('limits.max_files_per_batch', 50)
            );
    }

    public function test_admin_photo_upload_uses_super_admin_upload_and_watermark_settings(): void
    {
        Storage::fake('local');

        Setting::create(['key' => 'upload_max_file_size_mb', 'value' => '10', 'description' => 'Fixture setting']);
        Setting::create(['key' => 'upload_max_files_per_batch', 'value' => '3', 'description' => 'Fixture setting']);
        Setting::create(['key' => 'watermark_text', 'value' => 'Studio Mark', 'description' => 'Fixture setting']);
        Setting::create(['key' => 'watermark_opacity', 'value' => '40', 'description' => 'Fixture setting']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);

        $this->actingAs($admin)
            ->get(route('admin.photos.upload'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('limits.max_file_size_mb', 10)
                ->where('limits.max_files_per_batch', 3)
            );

        $this->mock(WatermarkService::class, function ($mock): void {
            $mock->shouldReceive('createPreview')
                ->once()
                ->with(\Mockery::type('string'), \Mockery::type('string'), 'Studio Mark', 40);
        });

        $this->actingAs($admin)
            ->post(route('admin.photos.store'), [
                'event_id' => $event->id,
                'photos' => [
                    UploadedFile::fake()->image('configured.jpg', 800, 600),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '1 foto berhasil diupload, 0 foto gagal.');
    }

    public function test_admin_can_bulk_upload_photos_to_owned_event(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['name' => 'Pesta Pora']);

        $this->actingAs($admin)
            ->post(route('admin.photos.store'), [
                'event_id' => $event->id,
                'photos' => [
                    UploadedFile::fake()->image('first.jpg', 800, 600),
                    UploadedFile::fake()->image('second.png', 640, 480),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 foto berhasil diupload, 0 foto gagal.')
            ->assertSessionHas('upload_result.success_count', 2);

        $this->assertDatabaseCount('photos', 2);
        $this->assertDatabaseHas('photos', [
            'event_id' => $event->id,
            'filename' => 'pesta pora-01.jpg',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('photos', [
            'event_id' => $event->id,
            'filename' => 'pesta pora-02.png',
            'sort_order' => 2,
        ]);
        Photo::all()->each(function (Photo $photo) use ($event): void {
            $this->assertSame($event->id, $photo->event_id);
            Storage::disk('local')->assertExists($photo->original_path);
            Storage::disk('local')->assertExists($photo->watermarked_path);
        });
    }

    public function test_admin_can_not_upload_photos_to_other_admin_event(): void
    {
        Storage::fake('local');

        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $otherEvent = $this->makeEvent($otherAdmin);

        $this->actingAs($admin)
            ->post(route('admin.photos.store'), [
                'event_id' => $otherEvent->id,
                'photos' => [
                    UploadedFile::fake()->image('blocked.jpg', 800, 600),
                ],
            ])
            ->assertSessionHasErrors('event_id');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_failed_watermark_does_not_create_visible_photo_record(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);

        $this->mock(WatermarkService::class, function ($mock): void {
            $mock->shouldReceive('createPreview')
                ->once()
                ->andThrow(new RuntimeException('Watermark failed.'));
        });

        $this->actingAs($admin)
            ->post(route('admin.photos.store'), [
                'event_id' => $event->id,
                'photos' => [
                    UploadedFile::fake()->image('broken.jpg', 800, 600),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('warning', '0 foto berhasil diupload, 1 foto gagal.')
            ->assertSessionHas('upload_result.failed_count', 1);

        $this->assertDatabaseCount('photos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_admin_preview_can_show_owned_draft_photo(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin, ['is_published' => false]);
        $photo = $this->makePhoto($event);
        Storage::disk('local')->put($photo->watermarked_path, 'preview');

        $this->actingAs($admin)
            ->get(route('admin.photos.preview', $photo))
            ->assertOk();
    }

    public function test_admin_can_delete_photo_without_order_items(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);
        Storage::disk('local')->put($photo->original_path, 'original');
        Storage::disk('local')->put($photo->watermarked_path, 'preview');

        $this->actingAs($admin)
            ->delete(route('admin.photos.destroy', $photo))
            ->assertRedirect()
            ->assertSessionHas('success', 'Foto berhasil dihapus.');

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('local')->assertMissing($photo->original_path);
        Storage::disk('local')->assertMissing($photo->watermarked_path);
    }

    public function test_admin_can_not_delete_photo_with_order_items(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);
        $order = $this->makeOrder($visitor, $event);
        OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $photo->id,
            'price' => 25000,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.photos.destroy', $photo))
            ->assertRedirect()
            ->assertSessionHas('error', 'Foto tidak dapat dihapus karena sudah masuk order item.');

        $this->assertNotNull($photo->fresh());
    }

    public function test_other_admin_can_not_delete_photo(): void
    {
        [$admin, $otherAdmin] = User::factory()->count(2)->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($otherAdmin);
        $photo = $this->makePhoto($event);

        $this->actingAs($admin)
            ->delete(route('admin.photos.destroy', $photo))
            ->assertForbidden();

        $this->assertNotNull($photo->fresh());
    }

    private function makeEvent(User $admin, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'admin_id' => $admin->id,
            'name' => 'Event Fixture',
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
            'original_path' => "photos/original/{$event->admin_id}/{$event->id}/test.jpg",
            'watermarked_path' => "photos/watermarked/{$event->admin_id}/{$event->id}/test.jpg",
            'filename' => 'test.jpg',
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ], $overrides));
    }

    private function makeOrder(User $visitor, Event $event): Order
    {
        return Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-ADMIN-PHOTO-0001',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);
    }
}
