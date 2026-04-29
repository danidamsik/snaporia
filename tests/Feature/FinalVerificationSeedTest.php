<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinalVerificationSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_seed_data_provides_all_demo_records_and_dummy_files(): void
    {
        $this->assertDatabaseCount('users', 9);
        $this->assertDatabaseCount('events', 5);
        $this->assertDatabaseCount('photos', 19);
        $this->assertDatabaseCount('orders', 5);
        $this->assertDatabaseCount('order_items', 14);
        $this->assertDatabaseCount('transactions', 5);
        $this->assertDatabaseCount('settings', 9);

        foreach ([1, 2, 7, 8, 9, 10] as $photoId) {
            $photo = Photo::findOrFail($photoId);

            $this->assertTrue(Storage::disk('local')->exists($photo->original_path));
            $this->assertTrue(Storage::disk('local')->exists($photo->watermarked_path));
        }
    }

    public function test_seeded_demo_accounts_can_login_while_inactive_account_is_blocked(): void
    {
        $expectations = [
            'superadmin@snaporia.test' => route('super-admin.dashboard'),
            'arka@snaporia.test' => route('admin.dashboard'),
            'lensa@snaporia.test' => route('admin.dashboard'),
            'momentika@snaporia.test' => route('admin.dashboard'),
            'rani@example.test' => route('events.index'),
            'dimas@example.test' => route('events.index'),
            'sinta@example.test' => route('events.index'),
            'bima@example.test' => route('events.index'),
        ];

        foreach ($expectations as $email => $redirect) {
            $user = User::where('email', $email)->firstOrFail();

            $response = $this->post('/login', [
                'email' => $email,
                'password' => 'password',
            ]);

            $this->assertAuthenticatedAs($user);
            $response->assertRedirect(route('dashboard'));
            $this->get(route('dashboard'))->assertRedirect($redirect);

            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }

        $response = $this->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_seeded_public_pages_only_show_published_events_and_support_search(): void
    {
        $response = $this->get(route('events.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Events/Index')
                ->has('events.data', 3)
                ->where('events.data.0.name', 'Wisuda Universitas Nusantara 2026')
            );

        $this->assertStringNotContainsString('Lomba Tari Pelajar', $response->getContent());
        $this->assertStringNotContainsString('Wedding Nara & Galih', $response->getContent());

        $this->get(route('events.index', ['q' => 'seminar-003']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.id', 2)
                ->where('events.data.0.name', 'Seminar Digital Creative 2026')
            );

        $this->get(route('events.index', ['date' => '2026-01-18', 'location' => 'Makassar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.id', 3)
            );

        $this->get(route('events.show', Event::findOrFail(4)))->assertNotFound();
        $this->get(route('events.show', Event::findOrFail(5)))->assertNotFound();
    }

    public function test_seeded_admin_and_visitor_data_access_stays_isolated(): void
    {
        $arka = User::where('email', 'arka@snaporia.test')->firstOrFail();
        $lensa = User::where('email', 'lensa@snaporia.test')->firstOrFail();
        $rani = User::where('email', 'rani@example.test')->firstOrFail();
        $dimas = User::where('email', 'dimas@example.test')->firstOrFail();

        $this->actingAs($arka)
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 2)
            );

        $this->actingAs($arka)
            ->get(route('admin.events.edit', Event::findOrFail(3)))
            ->assertForbidden();

        $this->actingAs($lensa)
            ->get(route('admin.events.edit', Event::findOrFail(1)))
            ->assertForbidden();

        $this->actingAs($rani)
            ->get(route('visitor.orders.show', Order::findOrFail(2)))
            ->assertForbidden();

        $this->actingAs($dimas)
            ->get(route('visitor.orders.photos.download', [Order::findOrFail(1), Photo::findOrFail(1)]))
            ->assertForbidden();
    }

    public function test_seeded_checkout_status_and_download_flows_match_demo_expectations(): void
    {
        $this->travelTo('2026-04-03 08:00:00');

        $rani = User::where('email', 'rani@example.test')->firstOrFail();
        $dimas = User::where('email', 'dimas@example.test')->firstOrFail();
        $sinta = User::where('email', 'sinta@example.test')->firstOrFail();
        $bima = User::where('email', 'bima@example.test')->firstOrFail();

        $this->actingAs($sinta)
            ->get(route('checkout.orders.show', Order::findOrFail(3)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Show')
                ->where('checkout.status', Order::STATUS_PENDING)
                ->where('checkout.payment.status', 'pending')
            );

        $this->actingAs($bima)
            ->get(route('checkout.orders.show', Order::findOrFail(4)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('checkout.status', Order::STATUS_EXPIRED)
                ->where('checkout.payment.status', 'expire')
            );

        $this->actingAs($rani)
            ->get(route('checkout.orders.show', Order::findOrFail(5)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('checkout.status', Order::STATUS_FAILED)
                ->where('checkout.payment.status', 'failure')
            );

        $this->actingAs($dimas)
            ->get(route('visitor.orders.show', Order::findOrFail(2)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.status', Order::STATUS_PAID)
                ->has('items.data', 4)
                ->where('items.data.0.download_url', route('visitor.orders.photos.download', [2, 7]))
            );

        $this->actingAs($rani)
            ->get(route('visitor.orders.photos.download', [Order::findOrFail(5), Photo::findOrFail(7)]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Download hanya tersedia untuk order paid.');

        $this->actingAs($rani)
            ->post(route('checkout.single.store'), [
                'photos' => [11],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'user_id' => $rani->id,
            'event_id' => 3,
            'type' => Order::TYPE_SINGLE,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 30000,
        ]);

        $this->actingAs($bima)
            ->post(route('checkout.package.store', Event::findOrFail(3)))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'user_id' => $bima->id,
            'event_id' => 3,
            'type' => Order::TYPE_PACKAGE,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 225000,
        ]);
    }

    public function test_seeded_reports_and_delete_rules_match_business_constraints(): void
    {
        $superAdmin = User::where('email', 'superadmin@snaporia.test')->firstOrFail();
        $arka = User::where('email', 'arka@snaporia.test')->firstOrFail();
        $freshAdmin = User::factory()->create([
            'name' => 'Admin Baru',
            'email' => 'admin-baru@example.test',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($arka)
            ->get(route('admin.reports.sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Sales')
                ->where('summary.total_revenue', 170000)
                ->where('summary.paid_orders_count', 2)
            );

        $this->actingAs($superAdmin)
            ->from(route('super-admin.users.index'))
            ->delete(route('super-admin.users.destroy', $arka))
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHas('error', 'Akun tidak dapat dihapus karena masih memiliki event atau order terkait.');

        $this->actingAs($superAdmin)
            ->from(route('super-admin.users.index'))
            ->delete(route('super-admin.users.destroy', $freshAdmin))
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertModelMissing($freshAdmin);
    }
}
