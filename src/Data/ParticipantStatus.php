<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use JsonSerializable;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;

/**
 * The registry as an operator reads it: who is registered, what they publish,
 * and **which of them are silent**.
 *
 * The silent ones are the point. A panel that lists only the modules that answer
 * is a panel that cannot show the gap, and the gap is the thing that makes an
 * erasure a false assurance.
 */
final readonly class ParticipantStatus implements JsonSerializable
{
    /** @param list<RequestKind> $handles */
    public function __construct(
        public string $name,
        public string $label,
        public bool $bound,
        public array $handles,
    ) {}

    public function isSilent(): bool
    {
        return ! $this->bound || $this->handles === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'bound' => $this->bound,
            'silent' => $this->isSilent(),
            'handles' => array_map(fn (RequestKind $k): string => $k->value, $this->handles),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
