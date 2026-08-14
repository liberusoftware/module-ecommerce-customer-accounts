<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;
use Liberu\Ecommerce\CustomerAccounts\Support\Reference;

it('folds case and whitespace on an address so one person is one person', function (): void {
    expect(Fingerprint::of('  Alice@Example.test '))->toBe(Fingerprint::of('alice@example.test'))
        ->and(Fingerprint::of('alice@example.test'))->toHaveLength(64)
        ->and(Fingerprint::of('alice@example.test'))->not->toContain('alice');
});

it('folds nothing on a secret, because case is part of one', function (): void {
    expect(Fingerprint::ofSecret('Token'))->not->toBe(Fingerprint::ofSecret('token'));
});

it('compares a secret without answering a question about it', function (): void {
    $secret = Reference::secret();

    expect(Fingerprint::matches(Fingerprint::ofSecret($secret), $secret))->toBeTrue()
        ->and(Fingerprint::matches(Fingerprint::ofSecret($secret), 'nope'))->toBeFalse();
});

it('mints prefixed references that are neither sortable nor repeatable', function (): void {
    $references = array_map(fn (): string => Reference::mint('par'), range(1, 50));

    expect(array_unique($references))->toHaveCount(50)
        ->and($references[0])->toStartWith('par_')
        ->and(Reference::secret())->toHaveLength(64);
});
