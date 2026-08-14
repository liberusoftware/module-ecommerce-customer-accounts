<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Contracts\ResolvesOrderHistory;
use Liberu\Ecommerce\CustomerAccounts\Contracts\VerifiesGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Queries\ListParticipants;
use Liberu\Ecommerce\CustomerAccounts\Queries\PersonOrderHistory;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\FakeOrderHistory;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\RecordingParticipant;

it('binds nothing at all', function (string $seam): void {
    expect(App::bound($seam))->toBeFalse();
})->with([
    [ParticipatesInPrivacyRequests::class],
    [ResolvesOrderHistory::class],
    [VerifiesGuestOrderClaim::class],
]);

it('answers unavailable, not empty, when order history is unbound', function (): void {
    $answer = (new PersonOrderHistory())('tenant-a', 'person-1');

    expect($answer->available)->toBeFalse()
        ->and($answer->isEmpty())->toBeFalse()
        ->and($answer->toArray())->toBe(['available' => false, 'orders' => []]);
});

it('keeps having ordered nothing distinct from not being able to say', function (): void {
    App::instance(ResolvesOrderHistory::class, new FakeOrderHistory(['tenant-a' => ['ORD-1']]));

    $has = (new PersonOrderHistory())('tenant-a', 'person-1');
    $hasNot = (new PersonOrderHistory())('tenant-b', 'person-1');

    expect($has->available)->toBeTrue()
        ->and($has->orders)->toHaveCount(1)
        ->and($has->orders[0]->orderReference)->toBe('ORD-1')
        ->and($hasNot->available)->toBeTrue()
        ->and($hasNot->isEmpty())->toBeTrue();
});

it('carries no money on an order summary', function (): void {
    App::instance(ResolvesOrderHistory::class, new FakeOrderHistory(['tenant-a' => ['ORD-1']]));

    $order = (new PersonOrderHistory())('tenant-a', 'person-1')->orders[0]->toArray();

    expect(array_keys($order))->toBe(['order_reference', 'placed_at', 'status']);
});

it('ignores something bound to the seam key that is not the seam', function (): void {
    App::instance(ResolvesOrderHistory::class, new stdClass());

    expect((new PersonOrderHistory())('tenant-a', 'person-1')->available)->toBeFalse();
});

it('shows the registry with the silent participants named', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant(), 'label' => 'Customer files'],
        'orders' => ['adapter' => null, 'label' => 'Orders'],
        'promotions' => ['adapter' => new RecordingParticipant(), 'handles' => ['erasure']],
    ]);

    $statuses = (new ListParticipants())();

    expect($statuses)->toHaveCount(3)
        ->and($statuses[0]->bound)->toBeTrue()
        ->and($statuses[0]->isSilent())->toBeFalse()
        ->and($statuses[0]->label)->toBe('Customer files')
        ->and($statuses[1]->bound)->toBeFalse()
        ->and($statuses[1]->isSilent())->toBeTrue()
        ->and($statuses[2]->handles)->toBe([RequestKind::Erasure])
        ->and($statuses[2]->toArray()['handles'])->toBe(['erasure']);
});

it('reads a participant registered with nothing at all as silent', function (): void {
    participants(['mystery' => ['adapter' => null, 'handles' => []]]);

    expect((new ListParticipants())()[0]->isSilent())->toBeTrue();
});

it('resolves nothing for a participant whose adapter is not the contract', function (): void {
    participants(['odd' => ['adapter' => new stdClass()]]);

    expect((new ListParticipants())()[0]->bound)->toBeFalse();
});
