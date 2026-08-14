<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

/**
 * A date and a timezone, never a bare timestamp.
 *
 * A statutory response period is counted in days in a jurisdiction: "within one
 * month" is a calendar answer. Stored as an instant, the same deadline is one
 * day earlier for a merchant in Auckland than for one in Los Angeles, and the
 * case that looks late is late because of where the server is.
 */
final readonly class Deadline implements JsonSerializable
{
    public function __construct(
        public string $date,
        public string $timezone,
    ) {}

    public static function in(CarbonImmutable $from, int $days, string $timezone): self
    {
        return new self(
            $from->setTimezone($timezone)->addDays($days)->toDateString(),
            $timezone,
        );
    }

    /** Late means the day is over where the deadline is counted, not where the server is. */
    public function hasPassed(CarbonImmutable $at): bool
    {
        return $at->greaterThan(
            CarbonImmutable::parse($this->date, $this->timezone)->endOfDay(),
        );
    }

    /** @return array{date: string, timezone: string} */
    public function toArray(): array
    {
        return ['date' => $this->date, 'timezone' => $this->timezone];
    }

    /** @return array{date: string, timezone: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
