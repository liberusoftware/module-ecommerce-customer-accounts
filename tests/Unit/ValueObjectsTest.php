<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Data\ClaimVerification;
use Liberu\Ecommerce\CustomerAccounts\Data\ExportArtefact;
use Liberu\Ecommerce\CustomerAccounts\Data\OrderHistoryAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\OrderSummary;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantRegistration;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Data\RequestProgress;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;

it('gives a completed answer a scope and gives the other two none', function (): void {
    $completed = ParticipationAnswer::completed(RequestScope::Tenant, ['rows' => 2], ['a' => 1], 'fine');
    $unavailable = ParticipationAnswer::unavailable('nothing bound');
    $failed = ParticipationAnswer::failed('threw');

    expect($completed->outcome)->toBe(ParticipantOutcome::Completed)
        ->and($completed->scopeApplied)->toBe(RequestScope::Tenant)
        ->and($completed->payload)->toBe(['a' => 1])
        ->and($unavailable->scopeApplied)->toBeNull()
        ->and($unavailable->outcome)->toBe(ParticipantOutcome::Unavailable)
        ->and($failed->outcome)->toBe(ParticipantOutcome::Failed);
});

it('keeps unavailable and failed both retryable and neither satisfied', function (): void {
    expect(ParticipantOutcome::Unavailable->isRetryable())->toBeTrue()
        ->and(ParticipantOutcome::Failed->isRetryable())->toBeTrue()
        ->and(ParticipantOutcome::Completed->isRetryable())->toBeFalse()
        ->and(ParticipantOutcome::Unavailable->isSatisfied())->toBeFalse()
        ->and(ParticipantOutcome::Unavailable->hasAnswered())->toBeTrue()
        ->and(ParticipantOutcome::Pending->hasAnswered())->toBeFalse();
});

it('never calls a case with nobody on it satisfied', function (): void {
    expect((new RequestProgress(0, 0, 0, 0, 0, [], []))->isSatisfied())->toBeFalse()
        ->and((new RequestProgress(2, 2, 0, 0, 0, [], []))->isSatisfied())->toBeTrue()
        ->and((new RequestProgress(2, 1, 1, 0, 0, ['x'], []))->isSatisfied())->toBeFalse();
});

it('serialises progress with the outstanding names on it', function (): void {
    $progress = new RequestProgress(3, 1, 1, 1, 0, ['b', 'c'], ['b']);

    expect($progress->toArray()['outstanding'])->toBe(['b', 'c'])
        ->and(json_decode(json_encode($progress), true)['mismatched'])->toBe(['b']);
});

it('reads a registry entry pessimistically', function (): void {
    $declared = ParticipantRegistration::fromConfig('reviews', ['label' => 'Reviews', 'handles' => ['erasure', 'nonsense']]);
    $bare = ParticipantRegistration::fromConfig('mystery', []);

    expect($declared->handles(RequestKind::Erasure))->toBeTrue()
        ->and($declared->handles(RequestKind::Access))->toBeFalse()
        ->and($declared->adapter)->toBeNull()
        ->and($bare->label)->toBe('mystery')
        ->and($bare->handles(RequestKind::Erasure))->toBeFalse();
});

it('keeps an unavailable history answer distinct from an empty one', function (): void {
    expect(OrderHistoryAnswer::unavailable()->isEmpty())->toBeFalse()
        ->and(OrderHistoryAnswer::of([])->isEmpty())->toBeTrue()
        ->and(OrderHistoryAnswer::of([new OrderSummary('ORD-1', CarbonImmutable::parse('2026-01-01T00:00:00Z'))])->isEmpty())->toBeFalse();
});

it('gives a not-matched verification nothing to decode', function (): void {
    $notMatched = ClaimVerification::notMatched();
    $unavailable = ClaimVerification::unavailable();

    expect(get_object_vars($notMatched))->toBe(['available' => true, 'matched' => false])
        ->and($unavailable->available)->toBeFalse()
        ->and(ClaimVerification::matched()->matched)->toBeTrue();
});

it('serialises a participation request without leaking anything it was not given', function (): void {
    $request = new ParticipationRequest(
        'par_1', 'tenant-a', 'person-1', RequestKind::Erasure, RequestScope::Tenant, 'actor', 'asked', CarbonImmutable::parse('2026-08-01T09:00:00Z'),
    );

    expect(json_decode(json_encode($request), true))->toBe([
        'request_reference' => 'par_1',
        'tenant_id' => 'tenant-a',
        'subject_ref' => 'person-1',
        'kind' => 'erasure',
        'scope' => 'tenant',
        'actor_ref' => 'actor',
        'reason' => 'asked',
        'requested_at' => '2026-08-01T09:00:00+00:00',
    ]);
});

it('marks an export artefact partial in its serialised form', function (): void {
    $artefact = new ExportArtefact('par_1', 'person-1', CarbonImmutable::parse('2026-08-02T00:00:00Z'), false, ['a' => []], ['b']);

    expect(json_decode(json_encode($artefact), true)['complete'])->toBeFalse()
        ->and(json_decode(json_encode($artefact), true)['missing_participants'])->toBe(['b']);
});

it('labels every enum case it publishes', function (): void {
    foreach (RequestKind::cases() as $case) {
        expect($case->label())->not->toBe('');
    }

    foreach (RequestState::cases() as $case) {
        expect($case->label())->not->toBe('');
    }

    foreach ([...RequestScope::cases(), ...LawfulBasis::cases(), ...ParticipantOutcome::cases(), ...ClaimState::cases()] as $case) {
        expect($case->label())->not->toBe('');
    }
});

it('knows which states are concluded and which claim state entitles', function (): void {
    expect(RequestState::Completed->isConcluded())->toBeTrue()
        ->and(RequestState::Partial->isConcluded())->toBeTrue()
        ->and(RequestState::Withdrawn->isConcluded())->toBeTrue()
        ->and(RequestState::Open->isConcluded())->toBeFalse()
        ->and(RequestState::InProgress->isConcluded())->toBeFalse()
        ->and(ClaimState::Granted->entitles())->toBeTrue()
        ->and(ClaimState::Pending->entitles())->toBeFalse()
        ->and(ClaimState::Revoked->entitles())->toBeFalse();
});

it('counts every claim outcome except issued and granted as a failure', function (): void {
    expect(ClaimAttemptOutcome::Refused->isFailure())->toBeTrue()
        ->and(ClaimAttemptOutcome::RateLimited->isFailure())->toBeTrue()
        ->and(ClaimAttemptOutcome::TokenMismatch->isFailure())->toBeTrue()
        ->and(ClaimAttemptOutcome::Expired->isFailure())->toBeTrue()
        ->and(ClaimAttemptOutcome::VerifierUnavailable->isFailure())->toBeTrue()
        ->and(ClaimAttemptOutcome::Issued->isFailure())->toBeFalse()
        ->and(ClaimAttemptOutcome::Granted->isFailure())->toBeFalse();
});

it('treats erasure as the destructive kind and access as not', function (): void {
    expect(RequestKind::Erasure->isDestructive())->toBeTrue()
        ->and(RequestKind::Access->isDestructive())->toBeFalse()
        ->and(Channel::Web->value)->toBe('web');
});
