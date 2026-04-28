<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_filter_users(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['name' => 'Arka Visual', 'role' => User::ROLE_ADMIN]);
        User::factory()->create(['name' => 'Rani Visitor', 'role' => User::ROLE_VISITOR]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.users.index', ['role' => User::ROLE_ADMIN, 'q' => 'Arka']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Users/Index')
                ->where('filters.role', User::ROLE_ADMIN)
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Arka Visual')
            );
    }

    public function test_non_super_admin_can_not_access_user_management(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('super-admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_admin_account(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'New Photographer',
                'email' => 'photographer@snaporia.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_SUPER_ADMIN,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $admin = User::where('email', 'photographer@snaporia.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_super_admin_can_update_admin_without_operational_data(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($superAdmin)
            ->put(route('super-admin.users.update', $admin), [
                'name' => 'Updated Admin',
                'email' => 'updated-admin@snaporia.test',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
                'is_active' => false,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $admin->refresh();

        $this->assertSame('Updated Admin', $admin->name);
        $this->assertSame('updated-admin@snaporia.test', $admin->email);
        $this->assertFalse($admin->is_active);
        $this->assertTrue(Hash::check('new-password', $admin->password));
    }

    public function test_admin_with_operational_data_can_not_be_updated(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->makeEvent($admin);

        $this->actingAs($superAdmin)
            ->put(route('super-admin.users.update', $admin), [
                'name' => 'Blocked Update',
                'email' => $admin->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
            ])
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHas('error', 'Akun Admin tidak dapat diubah karena sudah memiliki data operasional.');

        $this->assertNotSame('Blocked Update', $admin->refresh()->name);
    }

    public function test_super_admin_can_deactivate_user(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR, 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->patch(route('super-admin.users.deactivate', $visitor))
            ->assertRedirect();

        $this->assertFalse($visitor->refresh()->is_active);
    }

    public function test_user_with_operational_data_can_not_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->makeEvent($admin);
        $this->makeOrder($visitor, $event);

        $this->actingAs($superAdmin)
            ->delete(route('super-admin.users.destroy', $visitor))
            ->assertRedirect()
            ->assertSessionHas('error', 'Akun tidak dapat dihapus karena masih memiliki event atau order terkait.');

        $this->assertNotNull($visitor->fresh());
    }

    public function test_super_admin_can_delete_user_without_operational_data(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $visitor = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($superAdmin)
            ->delete(route('super-admin.users.destroy', $visitor))
            ->assertRedirect();

        $this->assertNull($visitor->fresh());
    }

    private function makeEvent(User $admin): Event
    {
        return Event::create([
            'admin_id' => $admin->id,
            'name' => 'Operational Event',
            'description' => 'Event fixture',
            'date' => '2026-04-28',
            'location' => 'Makassar',
            'price_per_photo' => 25000,
            'price_package' => 100000,
            'is_published' => true,
        ]);
    }

    private function makeOrder(User $visitor, Event $event): Order
    {
        return Order::create([
            'user_id' => $visitor->id,
            'order_code' => 'SNP-TEST-USER-0001',
            'type' => Order::TYPE_SINGLE,
            'event_id' => $event->id,
            'total_amount' => 25000,
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);
    }
}
