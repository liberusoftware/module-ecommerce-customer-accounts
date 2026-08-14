<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Data\ShareProof;
use Liberu\Ecommerce\CustomerAccounts\Events\SavedListShared;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\SavedListNotFound;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;
use Liberu\Ecommerce\CustomerAccounts\Support\Reference;

/**
 * Mint a share of one list.
 *
 * The share belongs to the **list**, so it answers the same thing to everybody
 * who holds it. The host's token lived on `users` with a unique index while the
 * wishlist was store-scoped and the shared route was public and
 * unauthenticated — so the merchant came from whichever host the visitor landed
 * on, and one link showed a different set of items per storefront, silently.
 *
 * Several shares of one list may exist at once, and each is revocable on its
 * own. That is not a feature for its own sake: one token per person means
 * revoking the link you sent your sister also revokes the one you sent your
 * colleague, so nobody ever revokes anything.
 */
final class ShareSavedList
{
    public function __invoke(string $listReference): ShareProof
    {
        $list = SavedList::query()->where('reference', $listReference)->first();

        if ($list === null) {
            throw SavedListNotFound::referenced($listReference);
        }

        $token = Reference::secret();

        $share = $list->shares()->create([
            'reference' => Reference::mint('shr'),
            'tenant_id' => $list->tenant_id,
            'owner_ref' => $list->owner_ref,
            'token_fingerprint' => Fingerprint::ofSecret($token),
        ]);

        Event::dispatch(new SavedListShared(
            shareReference: $share->reference,
            tenantId: $list->tenant_id,
            listReference: $list->reference,
            ownerRef: $list->owner_ref,
        ));

        return new ShareProof($share->reference, $token);
    }
}
