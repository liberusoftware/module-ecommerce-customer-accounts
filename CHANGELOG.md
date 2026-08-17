# Changelog

All notable changes to `liberusoftware/ecommerce-customer-accounts` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-08-17

### Fixed

- **Every relation that restates its parent's tenant is now guarded** — `Models\RestatesTenant`,
  adopted from `ecommerce-loyalty` `0.1.0` where the remedy was proven. `SavedList::items()`,
  `SavedList::shares()`, `OrderClaim::attempts()` and `PrivacyRequest::participants()` restated it
  as `where('tenant_id', (string) $this->tenant_id)`, which is correct through a loaded parent and
  wrong from a fresh one: `withCount()` and `whereHas()` build the relation from an instance whose
  `tenant_id` is `null`, so the predicate became `where('tenant_id', '')` and **every count would
  have silently returned zero**. Not previously reachable — nothing in `src/` counted — and a trap
  armed for the first surface that did. A merchant reading `0 saved items` acts on it exactly as
  confidently as on a wrong number.
- **`OrderClaim::attempts()` is restated as a column comparison when there is no loaded tenant**,
  because it is the one relation the guard's fallback does not cover: it joins on `order_reference`
  with no foreign key, so falling back to the join alone would have counted two merchants' attempts
  against order number 1001 as one merchant's. The tenant predicate is the only thing between them.

### Notes

- `CustodyTest` now asserts **both** halves for all four relations: the cross-merchant row is absent
  through a loaded parent, *and* `withCount()`/`whereHas()` return a **non-zero** count for the
  merchant that owns the rows. An assertion of zero is indistinguishable from the defect.
- `ModuleBoundaryRulesTest` fails if any model states `where('tenant_id', ...)` directly, so the
  next relation cannot be written the obvious way.

## [0.1.0] - 2026-08-14

### Added

- **The privacy request as a case with participants.** Subject, kind, lawful basis, both times, a
  deadline as a date and a timezone, a state, and one row per participant created at opening —
  before anybody is asked anything.
- **Partial completion.** `Completed` requires every registered participant to have completed;
  anything else that is finished is `Partial` and names the outstanding participants. There is no
  operator override.
- **Request scope as a first-class field**, and a recorded mismatch when a participant applied a
  different scope from the one asked for — in either direction.
- **`ParticipatesInPrivacyRequests`**, the plural seam: one contract, one implementation per module
  supplied by the host, registered by name in explicit configuration. This package depends on and
  calls no sibling module.
- **`ResolvesOrderHistory`** and **`VerifiesGuestOrderClaim`**, both optional and unbound by
  default; unbound, the first answers "not available" rather than an empty list and the second
  closes guest claiming rather than opening it.
- **Guest order claims as proof of possession**: a reference and an address open a claim, a token
  sent to the address on the order completes it, and a wrong reference and a wrong address are
  indistinguishable. Every attempt is recorded, including failures, and rate-limited two ways.
- **Saved lists** per person per merchant, holding product references and quantities and no price,
  with **shares as their own revocable aggregate** belonging to the list rather than to the person.
- **Erasure of this module's own rows on conclusion**, revoking every share the subject holds,
  deleting lists and items, redacting claims and leaving the attempt record intact.
- `CustodyPolicy`, `FindPrivacyRequest`, `ListParticipants`, `AssembleExport`, `ResolveShare`,
  `ListSavedLists`, `PersonOrderHistory` and `IsEntitledToOrder`.

### Notes

- Seven tables, all new and all prefixed `customer_accounts_`. No host table is adopted; the host's
  `wishlists` identity was `(user_id, product_id)` under a store scope that applies no predicate at
  all when no store is in context.
- The provider binds nothing. All three seams are optional by design, and a default implementation
  of the participant seam would turn "we could not reach it" into "done".

[0.2.0]: https://github.com/liberusoftware/module-ecommerce-customer-accounts/releases/tag/0.2.0
[0.1.0]: https://github.com/liberusoftware/module-ecommerce-customer-accounts/releases/tag/0.1.0
