<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PhotoPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Event::class => EventPolicy::class,
        Order::class => OrderPolicy::class,
        Photo::class => PhotoPolicy::class,
        Transaction::class => TransactionPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
