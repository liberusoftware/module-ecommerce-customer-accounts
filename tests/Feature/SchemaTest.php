<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\CustomerAccounts\Actions\AddItemToSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\CreateSavedList;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;

it('ships every table the module owns, all prefixed', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    ['customer_accounts_privacy_requests'],
    ['customer_accounts_privacy_request_participants'],
    ['customer_accounts_order_claims'],
    ['customer_accounts_claim_attempts'],
    ['customer_accounts_saved_lists'],
    ['customer_accounts_saved_list_items'],
    ['customer_accounts_saved_list_shares'],
]);

it('adopts no host table', function (): void {
    // Every table this module owns carries the module prefix, because it invented
    // every one of them. The host's `wishlists` is deliberately not adopted: its
    // identity was (user_id, product_id) under a store scope that applies no
    // predicate when no store is in context.
    foreach (['wishlists', 'payment_methods', 'gift_registries', 'browsing_history'] as $hostTable) {
        expect(Schema::hasTable($hostTable))->toBeFalse();
    }
});

it('carries tenant_id on every table in its own right, child tables included', function (string $table): void {
    expect(Schema::hasColumn($table, 'tenant_id'))->toBeTrue();
})->with([
    ['customer_accounts_privacy_requests'],
    ['customer_accounts_privacy_request_participants'],
    ['customer_accounts_order_claims'],
    ['customer_accounts_claim_attempts'],
    ['customer_accounts_saved_lists'],
    ['customer_accounts_saved_list_items'],
    ['customer_accounts_saved_list_shares'],
]);

it('holds no money column anywhere', function (string $table): void {
    // A saved list holds references and quantities. The price at the moment
    // something was saved is not a fact this module may assert, and a column for
    // it is how it would end up asserting one.
    foreach (Schema::getColumnListing($table) as $column) {
        expect($column)->not->toContain('price')
            ->and($column)->not->toContain('amount')
            ->and($column)->not->toContain('total')
            ->and($column)->not->toContain('minor')
            ->and($column)->not->toContain('currency');
    }
})->with([
    ['customer_accounts_privacy_requests'],
    ['customer_accounts_order_claims'],
    ['customer_accounts_saved_lists'],
    ['customer_accounts_saved_list_items'],
]);

it('stores a deadline as a date and a zone, never as an instant', function (): void {
    expect(Schema::hasColumn('customer_accounts_privacy_requests', 'deadline_date'))->toBeTrue()
        ->and(Schema::hasColumn('customer_accounts_privacy_requests', 'deadline_timezone'))->toBeTrue()
        ->and(Schema::hasColumn('customer_accounts_privacy_requests', 'deadline_at'))->toBeFalse();
});

it('stores both times: when the person asked and when we heard', function (): void {
    expect(Schema::hasColumn('customer_accounts_privacy_requests', 'requested_at'))->toBeTrue()
        ->and(Schema::hasColumn('customer_accounts_privacy_requests', 'recorded_at'))->toBeTrue();
});

it('holds no address, password, token or second factor in readable form', function (): void {
    $claims = Schema::getColumnListing('customer_accounts_order_claims');

    expect($claims)->toContain('email_fingerprint')
        ->and($claims)->not->toContain('email')
        ->and($claims)->not->toContain('token')
        ->and($claims)->not->toContain('password');

    expect(Schema::getColumnListing('customer_accounts_saved_list_shares'))->toContain('token_fingerprint');
    expect(Schema::getColumnListing('customer_accounts_claim_attempts'))->not->toContain('email');
});

it('reads its own defaults back from create, without a refresh', function (): void {
    // create() does not read column defaults back, so every default lives on the
    // model as well as on the column. A boolean that arrives null where the
    // docblock says bool passes every test in this package and fails in the
    // first surface that assigns it to a typed parameter.
    participants(['customers' => ['adapter' => null]]);

    $participant = openCase()->participants()->first();

    expect($participant->scope_mismatch)->toBeFalse()
        ->and($participant->attempts)->toBe(0)
        ->and($participant->outcome)->toBe(ParticipantOutcome::Pending);

    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $item = (new AddItemToSavedList())($list->reference, 'prod-9');

    expect($item->quantity)->toBe(1);
});
