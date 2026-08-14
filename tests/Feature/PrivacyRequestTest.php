<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Actions\WithdrawPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Events\PrivacyRequestOpened;
use Liberu\Ecommerce\CustomerAccounts\Events\PrivacyRequestWithdrawn;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ReauthenticationNotRecorded;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\RequestAlreadyConcluded;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\RecordingParticipant;

it('opens a case with a row for every registered participant, before anybody is asked', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'reviews' => ['adapter' => new RecordingParticipant()],
    ]);

    $request = openCase();

    expect($request->state)->toBe(RequestState::Open)
        ->and($request->participants()->count())->toBe(2)
        ->and($request->participants()->pluck('outcome')->all())
        ->each->toBe(ParticipantOutcome::Pending);
});

it('erases nothing and exports nothing at opening', function (): void {
    $adapter = new RecordingParticipant();
    participants(['customers' => ['adapter' => $adapter]]);

    openCase();

    expect($adapter->seen)->toBe([]);
});

it('refuses an erasure that cannot say how the subject was re-authenticated', function (): void {
    participants([]);

    expect(fn () => (new OpenPrivacyRequest())(draft(reauthenticatedVia: null)))
        ->toThrow(ReauthenticationNotRecorded::class);

    expect(fn () => (new OpenPrivacyRequest())(draft(reauthenticatedVia: '  ')))
        ->toThrow(ReauthenticationNotRecorded::class);
});

it('records the means of re-authentication and never the material', function (): void {
    participants([]);

    $request = (new OpenPrivacyRequest())(draft(reauthenticatedVia: 'password'));

    expect($request->reauthenticated_via)->toBe('password')
        ->and($request->getAttributes())->not->toHaveKey('password');
});

it('allows an access request without re-authentication, because it destroys nothing', function (): void {
    participants([]);

    $request = (new OpenPrivacyRequest())(draft(kind: RequestKind::Access, reauthenticatedVia: null));

    expect($request->kind)->toBe(RequestKind::Access);
});

it('answers unavailable immediately for a participant that publishes no half of this kind', function (): void {
    // Promotions publishes RedactCustomerFromRedemptions and no export at all.
    participants([
        'promotions' => ['adapter' => new RecordingParticipant(), 'handles' => ['erasure']],
    ]);

    $request = openCase(kind: RequestKind::Access);
    $participant = $request->participants()->first();

    expect($participant->outcome)->toBe(ParticipantOutcome::Unavailable)
        ->and($participant->note)->toBe('Publishes no access.')
        ->and($request->refresh()->state)->toBe(RequestState::InProgress);
});

it('treats a registered participant that declares nothing as publishing nothing', function (): void {
    participants(['mystery' => ['adapter' => new RecordingParticipant(), 'handles' => []]]);

    expect(openCase()->participants()->first()->outcome)->toBe(ParticipantOutcome::Unavailable);
});

it('counts the deadline in days in a named zone, from when the person asked', function (): void {
    participants([]);
    Config::set('customer-accounts.deadline_days', 30);
    Config::set('customer-accounts.deadline_timezone', 'Pacific/Auckland');

    $request = (new OpenPrivacyRequest())(draft(requestedAt: CarbonImmutable::parse('2026-08-01T20:00:00Z')));

    // 20:00 UTC on the 1st is already the 2nd in Auckland, so the deadline is
    // the 1st of September there and not the 31st of August.
    expect($request->deadline()->date)->toBe('2026-09-01')
        ->and($request->deadline()->timezone)->toBe('Pacific/Auckland');
});

it('mints an unguessable, unsortable reference', function (): void {
    participants([]);

    $first = openCase()->reference;
    $second = openCase()->reference;

    expect($first)->toStartWith('par_')
        ->and($first)->not->toBe($second)
        ->and(strlen($first))->toBe(28);
});

it('announces the opening with the participants it will be measured against', function (): void {
    Event::fake();
    participants(['customers' => ['adapter' => new RecordingParticipant()], 'reviews' => []]);

    openCase();

    Event::assertDispatched(PrivacyRequestOpened::class, fn (PrivacyRequestOpened $e): bool => $e->participants === ['customers', 'reviews']);
});

it('withdraws a case without pretending the erasures that ran did not', function (): void {
    Event::fake();
    participants(['customers' => ['adapter' => new RecordingParticipant()]]);

    $request = openCase();
    $withdrawn = (new WithdrawPrivacyRequest())($request->reference, 'changed their mind');

    expect($withdrawn->state)->toBe(RequestState::Withdrawn)
        ->and($withdrawn->concluded_at)->not->toBeNull();

    Event::assertDispatched(PrivacyRequestWithdrawn::class);

    expect(fn () => (new WithdrawPrivacyRequest())($request->reference))
        ->toThrow(RequestAlreadyConcluded::class);
});
