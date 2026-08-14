<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Tests\Fakes;

use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use RuntimeException;

/**
 * A host adapter, standing in for one module.
 *
 * `$scopeApplied` is the interesting knob: it is what the underlying module can
 * actually do, not what the case asked for, which is how the real fleet behaves
 * — Commerce Customers can only answer everywhere, Reviews only per tenant.
 */
final class RecordingParticipant implements ParticipatesInPrivacyRequests
{
    /** @var list<ParticipationRequest> */
    public array $seen = [];

    public function __construct(
        private readonly RequestScope $scopeApplied = RequestScope::Tenant,
        /** @var array<string, mixed>|null */
        private readonly ?array $payload = null,
        private readonly bool $throws = false,
        private readonly bool $unavailable = false,
    ) {}

    public function participate(ParticipationRequest $request): ParticipationAnswer
    {
        $this->seen[] = $request;

        if ($this->throws) {
            throw new RuntimeException('the module fell over');
        }

        if ($this->unavailable) {
            return ParticipationAnswer::unavailable('the module is not reachable');
        }

        return ParticipationAnswer::completed(
            $this->scopeApplied,
            ['rows' => 3],
            $this->payload,
        );
    }
}
