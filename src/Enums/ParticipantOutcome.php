<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/**
 * One participant's answer.
 *
 * Unavailable and Failed are both retryable and they are different facts:
 * unavailable means nothing was attempted (nothing is bound, or the module does
 * not publish this half at all), failed means something was attempted and threw.
 * Collapsing them loses the distinction between a module nobody installed and a
 * module that is broken.
 */
enum ParticipantOutcome: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Unavailable = 'unavailable';
    case Failed = 'failed';

    public function hasAnswered(): bool
    {
        return $this !== self::Pending;
    }

    /** Only Completed may be counted towards a case being done. */
    public function isSatisfied(): bool
    {
        return $this === self::Completed;
    }

    public function isRetryable(): bool
    {
        return $this !== self::Completed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not yet asked',
            self::Completed => 'Answered',
            self::Unavailable => 'Unavailable',
            self::Failed => 'Failed',
        };
    }
}
