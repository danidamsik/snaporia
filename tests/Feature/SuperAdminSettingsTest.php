<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_settings_page(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->seedSettings();

        $this->actingAs($superAdmin)
            ->get(route('super-admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Settings/Edit')
                ->has('settings', 9)
                ->where('settings.0.key', 'site_name')
                ->where('settings.0.value', 'Snaporia')
            );
    }

    public function test_super_admin_can_update_non_sensitive_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->seedSettings();

        $this->actingAs($superAdmin)
            ->put(route('super-admin.settings.update'), [
                'settings' => $this->settingsPayload([
                    'site_name' => 'Snaporia Pro',
                    'watermark_opacity' => '35',
                    'payment_pending_hours' => '48',
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Settings berhasil diperbarui.');

        $this->assertDatabaseHas('settings', [
            'key' => 'site_name',
            'value' => 'Snaporia Pro',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'watermark_opacity',
            'value' => '35',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'payment_pending_hours',
            'value' => '48',
        ]);
    }

    public function test_updated_site_settings_are_shared_as_application_branding(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->seedSettings();

        $this->actingAs($superAdmin)
            ->put(route('super-admin.settings.update'), [
                'settings' => $this->settingsPayload([
                    'site_name' => 'Studio Foto Pro',
                    'site_tagline' => 'Capture Every Detail.',
                ]),
            ])
            ->assertRedirect();

        $this->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('app.name', 'Studio Foto Pro')
                ->where('app.tagline', 'Capture Every Detail.')
            );
    }

    public function test_settings_reject_sensitive_or_unknown_keys(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->seedSettings();

        $this->actingAs($superAdmin)
            ->from(route('super-admin.settings.edit'))
            ->put(route('super-admin.settings.update'), [
                'settings' => $this->settingsPayload([
                    'midtrans_server_key' => 'secret-value',
                    'password' => 'secret-password',
                ]),
            ])
            ->assertRedirect(route('super-admin.settings.edit'))
            ->assertSessionHasErrors('settings');

        $this->assertDatabaseMissing('settings', [
            'key' => 'midtrans_server_key',
        ]);
        $this->assertDatabaseMissing('settings', [
            'key' => 'password',
        ]);
    }

    public function test_settings_validate_ranges(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->seedSettings();

        $this->actingAs($superAdmin)
            ->from(route('super-admin.settings.edit'))
            ->put(route('super-admin.settings.update'), [
                'settings' => $this->settingsPayload([
                    'upload_max_file_size_mb' => '99',
                    'upload_max_files_per_batch' => '99',
                    'watermark_opacity' => '0',
                ]),
            ])
            ->assertRedirect(route('super-admin.settings.edit'))
            ->assertSessionHasErrors([
                'settings.upload_max_file_size_mb',
                'settings.upload_max_files_per_batch',
                'settings.watermark_opacity',
            ]);
    }

    public function test_admin_and_visitor_can_not_access_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($admin)
            ->get(route('super-admin.settings.edit'))
            ->assertForbidden();

        $this->actingAs($visitor)
            ->get(route('super-admin.settings.edit'))
            ->assertForbidden();
    }

    private function seedSettings(): void
    {
        foreach ($this->settingsPayload() as $key => $value) {
            Setting::create([
                'key' => $key,
                'value' => $value,
                'description' => 'Fixture setting',
            ]);
        }
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'site_name' => 'Snaporia',
            'site_tagline' => 'Find Your Moments.',
            'public_gallery_per_page' => '24',
            'dashboard_table_per_page' => '20',
            'upload_max_file_size_mb' => '15',
            'upload_max_files_per_batch' => '50',
            'watermark_text' => 'Snaporia',
            'watermark_opacity' => '25',
            'payment_pending_hours' => '24',
        ], $overrides);
    }
}
