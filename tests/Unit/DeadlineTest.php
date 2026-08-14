<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Data\Deadline;

it('counts days in the jurisdiction rather than from the server', function (): void {
    $asked = CarbonImmutable::parse('2026-08-01T20:00:00Z');

    // The same instant is the 1st in London and already the 2nd in Auckland.
    expect(Deadline::in($asked, 30, 'Europe/London')->date)->toBe('2026-08-31')
        ->and(Deadline::in($asked, 30, 'Pacific/Auckland')->date)->toBe('2026-09-01');
});

it('is late only when the day is over where the deadline is counted', function (): void {
    $deadline = new Deadline('2026-08-31', 'Pacific/Auckland');

    // Auckland is twelve hours ahead, so the last moment of the 31st there is
    // 11:59:59 UTC — and a case that a UTC-shaped check would call on time for
    // another twelve hours is already late. This is exactly the twelve-hour
    // window a bare timestamp gets wrong, in whichever direction the merchant
    // happens to sit.
    expect($deadline->hasPassed(CarbonImmutable::parse('2026-08-31T11:00:00Z')))->toBeFalse()
        ->and($deadline->hasPassed(CarbonImmutable::parse('2026-08-31T12:00:00Z')))->toBeTrue()
        ->and($deadline->hasPassed(CarbonImmutable::parse('2026-09-05T00:00:00Z')))->toBeTrue();
});

it('serialises as a date and a zone', function (): void {
    $deadline = new Deadline('2026-08-31', 'UTC');

    expect($deadline->toArray())->toBe(['date' => '2026-08-31', 'timezone' => 'UTC'])
        ->and(json_decode(json_encode($deadline), true))->toBe(['date' => '2026-08-31', 'timezone' => 'UTC']);
});
