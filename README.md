# Ecommerce — Customer Accounts

[![Tests](https://github.com/liberusoftware/module-ecommerce-customer-accounts/actions/workflows/tests.yml/badge.svg)](https://github.com/liberusoftware/module-ecommerce-customer-accounts/actions/workflows/tests.yml)

**The person's standing to act** — the claim that a past transaction was theirs, the saved lists
they keep for themselves, and the privacy request (the case, its lawful basis, its participants,
its evidence and its completion) that other modules execute against their own rows.

It owns no order, no return, no review, no payment method, no customer file, no login, and **no
other module's erasure**. It is the smallest-data module in the fleet and the one with the most
neighbours, and that is the shape rather than a gap.

## Three things get called "your account"

1. **The login.** Identity's — a credential. This module never touches a password, a token or a
   second factor.
2. **The file.** Commerce Customers' — one merchant's record about a person: profile, addresses,
   groups, preferences, communication consent.
3. **The claim.** *Nobody's, and the application this replaced had no word for it.* The evidence
   that a person is entitled to see or act on a record some other module holds.

**We own the third.** A module that owns claims owns very little data and a great deal of
authority, and the discipline that follows is: **we never read another module's tables to satisfy a
claim; we hold the claim and ask the owner.**

## Why this module exists: nineteen modules, four erasure verbs, and no request

Read off the shipped repositories, not inferred:

| Module | Erasure | Signature | Scope | Export |
| --- | --- | --- | --- | --- |
| Commerce Customers | `RedactPerson` | `(personRef, reason, actorRef, ?occurredAt): array` | **cross-tenant** | `ExportPersonFile` |
| Attribution and Analytics | `RedactPerson` | `(tenantId, personRef, ?actorRef, ?reason): RedactionRecord` | tenant | `ExportPersonRecord` |
| Reviews and Ratings | `EraseAuthor` | `(tenantId, authorReference): ErasureReport` | tenant | `AuthorExport` (a query) |
| Promotions | `RedactCustomerFromRedemptions` | `(tenantId, customerRef): int` | tenant | **none** |
| Orders, Returns, Payment Operations, Shipping, Tax, Gift Cards | **none** | — | — | **none** |

Four verbs. Four return types. Three names for the same subject. **Two different scopes** — and
that is the one that hurts, because "erase me" already means two things in this fleet. Every one of
those modules was right on its own terms; nobody was coordinating, because coordination is a
capability and it had no owner.

This module is that owner, and its first job is not to erase anything — it is to **make the request
a thing that exists**. It depends on none of those packages and calls none of their actions.

## Installation

```bash
composer require liberusoftware/ecommerce-customer-accounts
```

The package ships no `extra.laravel.providers`; Composer boots nothing. The host's module manager
registers `CustomerAccountsServiceProvider` when the module is named in `MODULES_ENABLED`. It is not
on Packagist — see [`docs/adoption.md`](docs/adoption.md) for the repository entry a host must add,
and for the participant registry, which is part of installing it.

## Opening a privacy request

```php
use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Actions\{CommissionParticipant, ConcludePrivacyRequest, OpenPrivacyRequest};
use Liberu\Ecommerce\CustomerAccounts\Data\PrivacyRequestDraft;
use Liberu\Ecommerce\CustomerAccounts\Enums\{LawfulBasis, RequestKind, RequestScope};

$request = (new OpenPrivacyRequest())(new PrivacyRequestDraft(
    tenantId: 'tenant-7',
    subjectRef: 'person-42',
    kind: RequestKind::Erasure,
    scope: RequestScope::Everywhere,
    lawfulBasis: LawfulBasis::LegalObligation,
    requestedAt: CarbonImmutable::parse('2026-08-01T09:00:00Z'),
    // How the *host* satisfied itself this is the subject. A name, never material.
    reauthenticatedVia: 'password',
));

// Nothing has been erased. Each participant is a separate, retryable step —
// queue them, retry them, watch them.
(new CommissionParticipant())($request->reference, 'commerce-customers');
(new CommissionParticipant())($request->reference, 'reviews-and-ratings');

// Concluding cannot force Completed. If anybody is outstanding, it is Partial.
$concluded = (new ConcludePrivacyRequest())($request->reference);
$concluded->progress()->outstanding; // ['promotions']
```

## What a surface must say

```php
use Liberu\Ecommerce\CustomerAccounts\Queries\FindPrivacyRequest;

$view = (new FindPrivacyRequest())($reference);

$view->progress->completed;    // 3
$view->progress->participants; // 4
$view->progress->outstanding;  // ['promotions']
$view->state;                  // RequestState::Partial — never Completed
```

"Three of four systems have answered" is the sentence a person is owed. `{"success": true}` from a
function that wrote ten tables and had never heard of six modules is the sentence this module
exists to stop.

## Claiming a guest order

A reference plus the address it was placed with is **not** proof: both appear on a receipt, and a
receipt gets forwarded. The pair opens the claim; a token sent to the address *on the order*
completes it.

```php
use Liberu\Ecommerce\CustomerAccounts\Actions\{CompleteGuestOrderClaim, OpenGuestOrderClaim};

// Refuses identically for a wrong reference and a wrong address — the seam is
// never told which, so no surface can leak it. Rate-limited two ways, and every
// attempt is recorded, especially the failures.
$proof = (new OpenGuestOrderClaim())('tenant-7', 'ORD-1001', 'buyer@example.test', 'person-42');

// The host mails $proof->token to the address on the order. This module
// publishes the lifecycle, not the mail.
(new CompleteGuestOrderClaim())($proof->claimReference, $proof->token);
```

## Saved lists, and their shares

A saved list holds product references and quantities and **never a price** — it is not a saved
cart, which is a cart in a state and belongs to Cart. A share belongs to the *list*, not to the
person, so one link means one answer:

```php
use Liberu\Ecommerce\CustomerAccounts\Actions\{RevokeShare, ShareSavedList};
use Liberu\Ecommerce\CustomerAccounts\Queries\ResolveShare;

$proof = (new ShareSavedList())($list->reference);
(new ResolveShare())($proof->token, 'tenant-7');  // the list, or null
(new RevokeShare())($proof->shareReference);      // an act with a time and a reason
```

## Seams

All three are **consumed, optional and unbound by default**, and each unbound answer is a decision:

| Seam | Bound by | Unbound |
| --- | --- | --- |
| `ParticipatesInPrivacyRequests` | the host, one per participating module | the case is **partial**, and the module is named |
| `ResolvesOrderHistory` | Orders | **"not available"**, never an empty list |
| `VerifiesGuestOrderClaim` | Orders | claiming is **closed**, not open |

The provider binds nothing. Binding a default `ParticipatesInPrivacyRequests` would turn "we could
not reach it" into "done", which is the exact failure this module was extracted to end.

## Documentation

- [`docs/domain.md`](docs/domain.md) — the aggregates, the states, and what is deliberately not here
- [`docs/adoption.md`](docs/adoption.md) — installing it, and **writing the adapters**, with worked
  examples for the two most different published signatures
- [`docs/runbook.md`](docs/runbook.md) — running a privacy desk: retries, partials, the registry

## Licence

MIT. See [LICENSE.md](LICENSE.md).
