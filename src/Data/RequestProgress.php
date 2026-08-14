<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use JsonSerializable;

/**
 * "Three of four systems have answered."
 *
 * The sentence a person is owed instead of "done", and the reason this module
 * exists. It is computed from the participant rows rather than stored, so there
 * is no counter to drift: `outstanding` names the participants that have not
 * completed, and a surface that wants to say something reassuring has to say it
 * about a list it can see.
 */
final readonly class RequestProgress implements JsonSerializable
{
    /**
     * @param  list<string>  $outstanding
     * @param  list<string>  $mismatched
     */
    public function __construct(
        public int $participants,
        public int $completed,
        public int $unavailable,
        public int $failed,
        public int $pending,
        public array $outstanding,
        public array $mismatched,
    ) {}

    public function isSatisfied(): bool
    {
        return $this->participants > 0 && $this->completed === $this->participants;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'participants' => $this->participants,
            'completed' => $this->completed,
            'unavailable' => $this->unavailable,
            'failed' => $this->failed,
            'pending' => $this->pending,
            'outstanding' => $this->outstanding,
            'mismatched' => $this->mismatched,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
