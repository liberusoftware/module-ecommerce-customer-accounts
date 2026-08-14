<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\PrivacyRequestNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\WrongRequestKind;
use Liberu\Ecommerce\CustomerAccounts\Queries\AssembleExport;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\RecordingParticipant;

it('assembles an export from what participants contributed', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant(payload: ['file' => ['name' => 'A. Person']])],
        'reviews' => ['adapter' => new RecordingParticipant(payload: ['reviews' => []])],
    ]);

    $request = openCase(kind: RequestKind::Access);
    (new CommissionParticipant())($request->reference, 'customers');
    (new CommissionParticipant())($request->reference, 'reviews');

    $artefact = (new AssembleExport())($request->reference);

    expect($artefact->complete)->toBeTrue()
        ->and($artefact->missing)->toBe([])
        ->and($artefact->contributions)->toHaveKeys(['customers', 'reviews'])
        ->and($artefact->contributions['customers'])->toBe(['file' => ['name' => 'A. Person']]);
});

it('delivers a partial export as explicitly partial, naming who did not answer', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant(payload: ['file' => []])],
        'promotions' => ['adapter' => new RecordingParticipant(), 'handles' => ['erasure']],
        'orders' => ['adapter' => null],
    ]);

    $request = openCase(kind: RequestKind::Access);
    (new CommissionParticipant())($request->reference, 'customers');
    (new CommissionParticipant())($request->reference, 'orders');

    $artefact = (new AssembleExport())($request->reference);

    expect($artefact->complete)->toBeFalse()
        ->and($artefact->missing)->toBe(['orders', 'promotions'])
        ->and($artefact->toArray()['complete'])->toBeFalse()
        ->and($artefact->toArray()['missing_participants'])->toBe(['orders', 'promotions']);
});

it('is not complete when nothing was registered', function (): void {
    participants([]);

    $artefact = (new AssembleExport())(openCase(kind: RequestKind::Access)->reference);

    expect($artefact->complete)->toBeFalse()
        ->and($artefact->contributions)->toBe([]);
});

it('counts a participant that completed without a payload as missing', function (): void {
    // Completed and contributed nothing is not the same as contributed nothing
    // to contribute. An export that silently omits it is the host's defect: its
    // exporter and its erasure kept two whitelists and they diverged.
    participants(['customers' => ['adapter' => new RecordingParticipant(payload: null)]]);

    $request = openCase(kind: RequestKind::Access);
    (new CommissionParticipant())($request->reference, 'customers');

    expect((new AssembleExport())($request->reference)->missing)->toBe(['customers']);
});

it('refuses to assemble an export out of an erasure', function (): void {
    participants([]);

    expect(fn () => (new AssembleExport())(openCase()->reference))->toThrow(WrongRequestKind::class);
    expect(fn () => (new AssembleExport())('par_nope'))->toThrow(PrivacyRequestNotFound::class);
});
