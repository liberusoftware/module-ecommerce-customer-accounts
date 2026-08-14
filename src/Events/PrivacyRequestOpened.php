<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * A case exists. The host's equivalent moment was a function call, which is why
 * a person whose erasure failed halfway had nothing to quote at anyone.
 *
 * @param  list<string>  $participants  the registered participants, named at opening
 */
final readonly class PrivacyRequestOpened
{
    /** @param list<string> $participants */
    public function __construct(
        public string $reference,
        public string $tenantId,
        public string $subjectRef,
        public RequestKind $kind,
        public RequestScope $scope,
        public array $participants,
    ) {}
}
