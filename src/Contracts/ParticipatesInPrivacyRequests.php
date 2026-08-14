<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Contracts;

use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;

/**
 * "Answer this privacy request for your own rows."
 *
 * The plural seam, and the first in the fleet: one implementation per
 * participating module, written by the **host**, registered by name. This module
 * calls this interface and nothing else. It does not call `RedactPerson`,
 * `EraseAuthor` or `RedactCustomerFromRedemptions`, does not require any of the
 * packages that publish them, and installs and tests with none of them present —
 * which is not fastidiousness: six of the modules holding personal data in this
 * fleet publish no erasure at all, and a coordinator that hard-depends on the
 * four that do would be uninstallable for the reason that it coordinates.
 *
 * The fleet it has to adapt onto, read off the repositories:
 *
 * | Module                 | Verb                             | Signature                                              | Scope        |
 * |------------------------|----------------------------------|--------------------------------------------------------|--------------|
 * | Commerce Customers     | RedactPerson                     | (personRef, reason, actorRef, ?occurredAt): array      | cross-tenant |
 * | Attribution & Analytics| RedactPerson                     | (tenantId, personRef, ?actorRef, ?reason): Record      | per tenant   |
 * | Reviews and Ratings    | EraseAuthor                      | (tenantId, authorReference): ErasureReport             | per tenant   |
 * | Promotions             | RedactCustomerFromRedemptions    | (tenantId, customerRef): int                           | per tenant   |
 *
 * Four verbs, four return types, three names for the same subject and two
 * scopes. The adapter absorbs all four differences; this interface knows about
 * none of them. Worked adapters for the two most different signatures are in
 * docs/adoption.md.
 *
 * **Three answers, not two**, following the shape wave 13 set. Unavailable is
 * not failure and is emphatically not success: it is the answer that makes the
 * case *partial*, names the module that did not answer, and stops "your data has
 * been erased" being said about rows nobody touched.
 *
 * An implementation must not throw to mean unavailable — but if it does, the
 * commission records Failed rather than losing the case, because a participant
 * that raises is a participant that answered badly, not a request that never
 * happened.
 */
interface ParticipatesInPrivacyRequests
{
    public function participate(ParticipationRequest $request): ParticipationAnswer;
}
