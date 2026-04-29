<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_monitor_events_from_all_admins_with_filters(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $arka = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Arka Visual']);
        $lensa = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Lensa Cerita']);
        $published = $this->makeEvent($arka, [
            'name' => 'Wisuda Nusantara',
            'date' => '2026-03-14',
            'location' => 'Balai Kartini',
            'is_published' => true,
        ]);
        $draft = $this->makeEvent($lensa, [
            'name' => 'Konser Senja',
            'date' => '2026-04-20',
            'location' => 'Makassar',
            'is_published' => false,
        ]);
        $this->makePhoto($published, ['filename' => 'wisuda-rani.jpg']);
        $this->makePhoto($draft, ['filename' => 'konser-private.jpg']);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Monitoring/Events')
                ->has('events.data', 2)
            );

        $this->actingAs($superAdmin)
            ->get(route('super-admin.events.index', [
                'admin_id' => $arka->id,
                'status' => 'published',
                'q' => 'wisuda-rani',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.id', $published->id)
                ->where('events.data.0.admin.id', $arka->id)
                ->where('events.data.0.is_published', true)
                ->where('events.data.0.photos_count', 1)
            );
    }

    public function test_super_admin_can_monitor_photos_without_exposing_original_paths(): void
    {
        Storage::fake('local');

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $arka = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Arka Visual']);
        $event = $this->makeEvent($arka, ['name' => 'Seminar Digital']);
        $readyPhoto = $this->makePhoto($event, [
            'filename' => 'seminar-001.jpg',
            'original_path' => 'photos/original/private/seminar-001.jpg',
            'watermarked_path' => 'photos/watermarked/public/seminar-001.jpg',
        ]);
        $missingPreviewPhoto = $this->makePhoto($event, [
            'filename' => 'seminar-002.jpg',
            'original_path' => 'photos/original/private/seminar-002.jpg',
            'watermarked_path' => 'photos/watermarked/public/seminar-002.jpg',
        ]);

        Storage::disk('local')->put($readyPhoto->watermarked_path, 'preview-content');

        $response = $this->actingAs($superAdmin)
            ->get(route('super-admin.photos.index', [
                'admin_id' => $arka->id,
                'event_id' => $event->id,
                'status' => 'ready',
                'q' => 'seminar',
            ]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Monitoring/Photos')
                ->has('photos.data', 1)
                ->where('photos.data.0.id', $readyPhoto->id)
                ->where('photos.data.0.status', 'ready')
                ->where('photos.data.0.admin.id', $arka->id)
                ->where('photos.data.0.event.id', $event->id)
            );

        $this->assertStringNotContainsString($readyPhoto->original_path, $response->getContent());
        $this->assertStringNotContainsString($missingPreviewPhoto->original_path, $response->getContent());
        $this->assertStringNotContainsString('original_path', $response->getContent());

        $this->actingAs($superAdmin)
            ->get(route('super-admin.photos.preview', $readyPhoto))
            ->assertOk();
    }

    public function test_non_super_admin_can_not_access_monitoring_pages(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $photo = $this->makePhoto($event);

        Storage::disk('local')->put($photo->watermarked_path, 'preview-content');

        $this->actingAs($admin)
            ->get(route('super-admin.events.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('super-admin.photos.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('super-admin.photos.preview', $photo))
            ->assertForbidden();
    }

    private function makeEvent(User $admin, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'admin_id' => $admin->id,
            'name' => 'Monitoring Event',
            'description' => 'Fixture',
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
            'original_path' => "photos/original/{$event->admin_id}/{$event->id}/photo.jpg",
            'watermarked_path' => "photos/watermarked/{$event->admin_id}/{$event->id}/photo.jpg",
            'filename' => 'photo.jpg',
            'file_size' => 1000,
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ], $overrides));
    }
}
