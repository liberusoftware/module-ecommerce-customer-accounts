<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Contracts;

use Liberu\Ecommerce\CustomerAccounts\Data\ClaimVerification;

/**
 * "Does this order reference exist at this merchant, placed with this address?"
 *
 * Orders' to bind, and the answer is a single verdict rather than a lookup: the
 * seam is never asked *which* order, never asked whose it is, and never returns
 * anything about it. It answers matched, not matched, or unavailable.
 *
 * **Not matched is one answer for two conditions on purpose.** A wrong reference
 * and a wrong address must be indistinguishable, or the endpoint above this
 * enumerates order references for anybody with a mailbox. Making them the same
 * value *here* means no surface can leak the difference by decoding a message,
 * and no implementer can helpfully split them later without changing this file.
 *
 * Unbound, claiming is **closed** rather than open: a claim that cannot be
 * verified is refused and the surface says the feature is unavailable. The
 * opposite default — accept the claim, verify later — grants entitlement on an
 * unchecked assertion, which is the failure this seam exists to prevent.
 */
interface VerifiesGuestOrderClaim
{
    public function verifyClaim(string $tenantId, string $orderReference, string $email): ClaimVerification;
}
