<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantRegistration;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantStatus;
use Liberu\Ecommerce\CustomerAccounts\Support\ParticipantRegistry;

/**
 * The registry, with the silent ones visible.
 *
 * This is the query the privacy desk is built around. An operator looking at a
 * partial case needs to know whether a module answered nothing because nobody
 * bound an adapter, because the module publishes no erasure at all, or because
 * it threw — and the first two are answerable before any case exists, which is
 * when somebody can still fix them.
 */
final class ListParticipants
{
    public function __construct(
        private readonly ParticipantRegistry $registry = new ParticipantRegistry(),
    ) {}

    /** @return list<ParticipantStatus> */
    public function __invoke(): array
    {
        return array_map(
            fn (ParticipantRegistration $r): ParticipantStatus => new ParticipantStatus(
                name: $r->name,
                label: $r->label,
                bound: $this->registry->resolve($r->name) !== null,
                handles: $r->handles,
            ),
            $this->registry->all(),
        );
    }
}
