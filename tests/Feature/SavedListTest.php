<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Actions\AddItemToSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\CreateSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\RemoveItemFromSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\RevokeShare;
use Liberu\Ecommerce\CustomerAccounts\Actions\ShareSavedList;
use Liberu\Ecommerce\CustomerAccounts\Events\SavedListShared;
use Liberu\Ecommerce\CustomerAccounts\Events\ShareRevoked;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\InvalidQuantity;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\SavedListNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ShareNotFound;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListShare;
use Liberu\Ecommerce\CustomerAccounts\Queries\ListSavedLists;
use Liberu\Ecommerce\CustomerAccounts\Queries\ResolveShare;

it('keeps a list per person per merchant, with the merchant in its identity', function (): void {
    $a = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $b = (new CreateSavedList())('tenant-b', 'person-1', 'Wishlist');

    expect($a->id)->not->toBe($b->id);

    // Asking twice is not two lists: the host's equivalent lookup could resolve
    // into a different merchant's row entirely when no store was in context.
    expect((new CreateSavedList())('tenant-a', 'person-1', 'Wishlist')->id)->toBe($a->id);
});

it('holds a product reference and a quantity and no price', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $item = (new AddItemToSavedList())($list->reference, 'prod-9', 2, 'for mum');

    expect($item->product_ref)->toBe('prod-9')
        ->and($item->quantity)->toBe(2)
        ->and($item->tenant_id)->toBe('tenant-a')
        ->and(array_keys($item->getAttributes()))->not->toContain('price');
});

it('defaults a quantity of one and refuses less than one', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');

    expect((new AddItemToSavedList())($list->reference, 'prod-9')->quantity)->toBe(1);
    expect(fn () => (new AddItemToSavedList())($list->reference, 'prod-9', 0))->toThrow(InvalidQuantity::class);
});

it('adds the same product once, updating rather than duplicating', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');

    (new AddItemToSavedList())($list->reference, 'prod-9', 1);
    (new AddItemToSavedList())($list->reference, 'prod-9', 4);

    expect($list->items()->count())->toBe(1)
        ->and($list->items()->first()->quantity)->toBe(4);
});

it('removes an item and says how many went', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    (new AddItemToSavedList())($list->reference, 'prod-9');

    expect((new RemoveItemFromSavedList())($list->reference, 'prod-9'))->toBe(1)
        ->and((new RemoveItemFromSavedList())($list->reference, 'prod-9'))->toBe(0);
});

it('refuses to act on a list that does not exist', function (): void {
    expect(fn () => (new AddItemToSavedList())('lst_nope', 'prod-9'))->toThrow(SavedListNotFound::class);
    expect(fn () => (new RemoveItemFromSavedList())('lst_nope', 'prod-9'))->toThrow(SavedListNotFound::class);
    expect(fn () => (new ShareSavedList())('lst_nope'))->toThrow(SavedListNotFound::class);
    expect(fn () => (new RevokeShare())('shr_nope'))->toThrow(ShareNotFound::class);
});

it('shares a list by minting a token that belongs to the list', function (): void {
    Event::fake();
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    (new AddItemToSavedList())($list->reference, 'prod-9', 3);

    $proof = (new ShareSavedList())($list->reference);
    $view = (new ResolveShare())($proof->token, 'tenant-a');

    expect($view->reference)->toBe($list->reference)
        ->and($view->items)->toHaveCount(1)
        ->and($view->items[0]->quantity)->toBe(3)
        ->and($view->liveShares)->toBe(1);

    Event::assertDispatched(SavedListShared::class);
});

it('mints several independent shares of one list', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');

    $first = (new ShareSavedList())($list->reference);
    $second = (new ShareSavedList())($list->reference);

    (new RevokeShare())($first->shareReference);

    // One token per person means revoking the link you sent your sister revokes
    // the one you sent your colleague, so nobody ever revokes anything.
    expect((new ResolveShare())($first->token))->toBeNull()
        ->and((new ResolveShare())($second->token))->not->toBeNull();
});

it('stores the share token only as a fingerprint', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $proof = (new ShareSavedList())($list->reference);

    expect(SavedListShare::query()->first()->token_fingerprint)->not->toBe($proof->token);
});

it('records a revocation as an act with a time and a reason', function (): void {
    Event::fake();
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $proof = (new ShareSavedList())($list->reference);

    $share = (new RevokeShare())($proof->shareReference, 'owner_revoked');

    expect($share->revoked_at)->not->toBeNull()
        ->and($share->revoked_reason)->toBe('owner_revoked')
        ->and($share->isLive())->toBeFalse();

    Event::assertDispatched(ShareRevoked::class);

    // Revoking twice is somebody clicking twice, not a failure.
    expect((new RevokeShare())($proof->shareReference)->revoked_reason)->toBe('owner_revoked');
});

it('answers nothing for a revoked token and for a token that never existed, identically', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $proof = (new ShareSavedList())($list->reference);
    (new RevokeShare())($proof->shareReference);

    expect((new ResolveShare())($proof->token))->toBeNull()
        ->and((new ResolveShare())('never-minted'))->toBeNull();
});

it('lists a person s lists at one merchant with their live share count', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    (new AddItemToSavedList())($list->reference, 'prod-9');
    (new ShareSavedList())($list->reference);
    (new CreateSavedList())('tenant-b', 'person-1', 'Elsewhere');

    $lists = (new ListSavedLists())('tenant-a', 'person-1');

    expect($lists)->toHaveCount(1)
        ->and($lists[0]->name)->toBe('Wishlist')
        ->and($lists[0]->liveShares)->toBe(1)
        ->and($lists[0]->toArray()['items'][0]['product_ref'])->toBe('prod-9');
});
