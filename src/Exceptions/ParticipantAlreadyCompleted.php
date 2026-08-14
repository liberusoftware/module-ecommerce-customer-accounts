<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * A completed participant is not re-commissioned. Erasure is not idempotent in
 * any useful sense — the second run has nothing to erase and would overwrite the
 * counts from the run that did the work.
 */
final class ParticipantAlreadyCompleted extends CustomerAccountsException
{
    public static function for(string $requestReference, string $participant): self
    {
        return new self("Participant [{$participant}] has already completed request [{$requestReference}].");
    }
}
