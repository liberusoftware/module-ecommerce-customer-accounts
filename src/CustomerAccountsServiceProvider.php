<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts;

use Illuminate\Support\ServiceProvider;

/**
 * The module's provider. Composer never boots it — `extra.laravel.providers` is
 * empty on purpose — and the host's module manager registers it only when the
 * module is named in MODULES_ENABLED.
 *
 * **It binds nothing.** All three seams are optional and unbound by default, and
 * each unbound seam has a defined, honest answer:
 *
 * - ParticipatesInPrivacyRequests unbound for a registered participant makes the
 *   case *partial*. Binding a default that answered "completed" would be the
 *   false assurance this whole module exists to prevent.
 * - ResolvesOrderHistory unbound renders "not available", never an empty list.
 * - VerifiesGuestOrderClaim unbound closes claiming, rather than opening it.
 *
 * The registry itself is config, so there is nothing to bind for it either: a
 * participant is resolved by name from `customer-accounts.participants` at the
 * moment it is commissioned, which is what makes a host's rebinding take effect
 * on the next retry rather than at boot.
 */
class CustomerAccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/customer-accounts.php', 'customer-accounts');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/customer-accounts.php' => $this->app->configPath('customer-accounts.php'),
            ], 'customer-accounts-config');
        }
    }
}
