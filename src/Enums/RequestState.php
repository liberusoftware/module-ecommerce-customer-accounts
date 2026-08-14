<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/**
 * Note what is not here: no "done" that a participant can reach on its own, and
 * no state that means "we think it worked". A case is Completed only when every
 * registered participant completed; anything else that is finished is Partial,
 * and Partial names who did not answer.
 */
enum RequestState: string
{
    /** Opened; no participant has answered yet. */
    case Open = 'open';

    /** At least one participant has answered and at least one has not completed. */
    case InProgress = 'in_progress';

    /** Every registered participant completed. The only state that may be read as "done". */
    case Completed = 'completed';

    /** Concluded with at least one participant that did not complete. */
    case Partial = 'partial';

    /** The subject withdrew it before it concluded. */
    case Withdrawn = 'withdrawn';

    public function isConcluded(): bool
    {
        return in_array($this, [self::Completed, self::Partial, self::Withdrawn], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Partial => 'Completed in part',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
