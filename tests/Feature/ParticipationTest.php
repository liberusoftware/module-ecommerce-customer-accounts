<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Actions\ConcludePrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Events\ParticipantAnswered;
use Liberu\Ecommerce\CustomerAccounts\Events\PrivacyRequestConcluded;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ParticipantAlreadyCompleted;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ParticipantNotRegistered;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\PrivacyRequestNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\RequestAlreadyConcluded;
use Liberu\Ecommerce\CustomerAccounts\Queries\FindPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\RecordingParticipant;

it('hands the participant the case and nothing else', function (): void {
    $adapter = new RecordingParticipant();
    participants(['customers' => ['adapter' => $adapter]]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');

    expect($adapter->seen)->toHaveCount(1);

    $seen = $adapter->seen[0];
    expect($seen->requestReference)->toBe($request->reference)
        ->and($seen->tenantId)->toBe('tenant-a')
        ->and($seen->subjectRef)->toBe('person-1')
        ->and($seen->kind)->toBe(RequestKind::Erasure)
        ->and($seen->scope)->toBe(RequestScope::Tenant)
        ->and($seen->actorRef)->toBe('actor-1');
});

it('completes the case only when every participant has completed', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'reviews' => ['adapter' => new RecordingParticipant()],
    ]);

    $request = openCase();

    (new CommissionParticipant())($request->reference, 'customers');
    expect($request->refresh()->state)->toBe(RequestState::InProgress);

    (new CommissionParticipant())($request->reference, 'reviews');
    expect($request->refresh()->state)->toBe(RequestState::Completed)
        ->and($request->concluded_at)->not->toBeNull();
});

it('is partial, naming the silent module, when one participant is registered and unbound', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'orders' => ['adapter' => null],
    ]);

    $request = openCase();

    (new CommissionParticipant())($request->reference, 'customers');
    $orders = (new CommissionParticipant())($request->reference, 'orders');

    expect($orders->outcome)->toBe(ParticipantOutcome::Unavailable)
        ->and($orders->note)->toBe('Registered, but nothing is bound to answer for it.');

    $concluded = (new ConcludePrivacyRequest())($request->reference);

    expect($concluded->state)->toBe(RequestState::Partial)
        ->and($concluded->progress()->outstanding)->toBe(['orders'])
        ->and($concluded->progress()->completed)->toBe(1)
        ->and($concluded->progress()->participants)->toBe(2);
});

it('never reports a case as done when a participant did not answer', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'orders' => ['adapter' => null],
    ]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');
    (new ConcludePrivacyRequest())($request->reference);

    // The whole wave in one assertion: the state a surface renders is never
    // Completed while somebody is outstanding, whatever else happened.
    expect($request->refresh()->state)->not->toBe(RequestState::Completed);
});

it('carries the outstanding participants on the conclusion event', function (): void {
    Event::fake();
    participants(['customers' => ['adapter' => null], 'reviews' => ['adapter' => null]]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');
    (new ConcludePrivacyRequest())($request->reference);

    Event::assertDispatched(
        PrivacyRequestConcluded::class,
        fn (PrivacyRequestConcluded $e): bool => $e->state === RequestState::Partial
            && $e->progress->outstanding === ['customers', 'reviews'],
    );
});

it('records a participant that threw as failed, and keeps the case alive', function (): void {
    participants(['reviews' => ['adapter' => new RecordingParticipant(throws: true)]]);

    $request = openCase();
    $participant = (new CommissionParticipant())($request->reference, 'reviews');

    expect($participant->outcome)->toBe(ParticipantOutcome::Failed)
        ->and($participant->note)->toContain('the module fell over')
        ->and($participant->attempts)->toBe(1)
        ->and($request->refresh()->state)->toBe(RequestState::InProgress);
});

it('retries a failed participant and completes on the retry', function (): void {
    participants(['reviews' => ['adapter' => new RecordingParticipant(throws: true)]]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'reviews');

    participants(['reviews' => ['adapter' => new RecordingParticipant()]]);

    $participant = (new CommissionParticipant())($request->reference, 'reviews');

    expect($participant->outcome)->toBe(ParticipantOutcome::Completed)
        ->and($participant->attempts)->toBe(2)
        ->and($request->refresh()->state)->toBe(RequestState::Completed);
});

it('refuses to re-commission a participant that already completed', function (): void {
    // Two participants, so the case is still open — the erasure that ran is not
    // rerunnable even while the case as a whole is unfinished.
    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'reviews' => ['adapter' => null],
    ]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');

    expect(fn () => (new CommissionParticipant())($request->reference, 'customers'))
        ->toThrow(ParticipantAlreadyCompleted::class);
});

it('records a scope mismatch rather than reconciling it', function (): void {
    // Commerce Customers erases everywhere because a person is a person. Asked
    // for one merchant, it did more than the case asked for, and that is a fact
    // rather than a rounding error.
    participants(['customers' => ['adapter' => new RecordingParticipant(scopeApplied: RequestScope::Everywhere)]]);

    $request = openCase(scope: RequestScope::Tenant);
    $participant = (new CommissionParticipant())($request->reference, 'customers');

    expect($participant->scope_applied)->toBe(RequestScope::Everywhere)
        ->and($participant->scope_mismatch)->toBeTrue()
        ->and($participant->outcome)->toBe(ParticipantOutcome::Completed)
        ->and($request->refresh()->progress()->mismatched)->toBe(['customers']);
});

it('records a mismatch in the other direction too', function (): void {
    // Reviews can only erase within one merchant. Asked to erase everywhere, it
    // completed at less than the requested reach.
    participants(['reviews' => ['adapter' => new RecordingParticipant(scopeApplied: RequestScope::Tenant)]]);

    $request = openCase(scope: RequestScope::Everywhere);
    $participant = (new CommissionParticipant())($request->reference, 'reviews');

    expect($participant->scope_mismatch)->toBeTrue()
        ->and($request->refresh()->state)->toBe(RequestState::Completed);
});

it('records no mismatch when the participant applied what was asked', function (): void {
    participants(['reviews' => ['adapter' => new RecordingParticipant(scopeApplied: RequestScope::Tenant)]]);

    $request = openCase(scope: RequestScope::Tenant);

    expect((new CommissionParticipant())($request->reference, 'reviews')->scope_mismatch)->toBeFalse();
});

it('treats a participant deleted from the registry mid-case as unavailable, not absent', function (): void {
    participants(['customers' => ['adapter' => new RecordingParticipant()]]);

    $request = openCase();
    Config::set('customer-accounts.participants', []);

    $participant = (new CommissionParticipant())($request->reference, 'customers');

    expect($participant->outcome)->toBe(ParticipantOutcome::Unavailable)
        ->and($participant->note)->toBe('No longer registered.')
        ->and($request->refresh()->state)->not->toBe(RequestState::Completed);
});

it('refuses to commission an unknown participant or an unknown case', function (): void {
    participants(['customers' => ['adapter' => new RecordingParticipant()]]);
    $request = openCase();

    expect(fn () => (new CommissionParticipant())($request->reference, 'nobody'))
        ->toThrow(ParticipantNotRegistered::class);

    expect(fn () => (new CommissionParticipant())('par_nope', 'customers'))
        ->toThrow(PrivacyRequestNotFound::class);
});

it('refuses to commission against a concluded case', function (): void {
    participants(['customers' => ['adapter' => new RecordingParticipant()]]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');

    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'late' => ['adapter' => new RecordingParticipant()],
    ]);

    expect(fn () => (new CommissionParticipant())($request->reference, 'late'))
        ->toThrow(RequestAlreadyConcluded::class);
});

it('cannot be forced to completed by concluding', function (): void {
    participants(['customers' => ['adapter' => null]]);

    $request = openCase();

    expect((new ConcludePrivacyRequest())($request->reference)->state)->toBe(RequestState::Partial);
});

it('is partial when nothing at all is registered, because nothing was asked', function (): void {
    participants([]);

    $request = openCase();

    expect($request->state)->toBe(RequestState::Open)
        ->and($request->progress()->isSatisfied())->toBeFalse()
        ->and((new ConcludePrivacyRequest())($request->reference)->state)->toBe(RequestState::Partial);
});

it('announces every answer with the scope it applied', function (): void {
    Event::fake();
    participants(['customers' => ['adapter' => new RecordingParticipant(scopeApplied: RequestScope::Everywhere)]]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');

    Event::assertDispatched(
        ParticipantAnswered::class,
        fn (ParticipantAnswered $e): bool => $e->scopeApplied === RequestScope::Everywhere && $e->scopeMismatch,
    );
});

it('shows every participant in the view, including the ones that answered nothing', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant()],
        'orders' => ['adapter' => null],
    ]);

    $request = openCase();
    (new CommissionParticipant())($request->reference, 'customers');
    (new CommissionParticipant())($request->reference, 'orders');

    $view = (new FindPrivacyRequest())($request->reference);

    expect($view)->not->toBeNull()
        ->and($view->participants)->toHaveCount(2)
        ->and($view->progress->outstanding)->toBe(['orders'])
        ->and($view->toArray()['participants'][1]['outcome'])->toBe('unavailable');

    expect((new FindPrivacyRequest())('par_nope'))->toBeNull();
});
