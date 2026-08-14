# The domain

## The boundary

**Customer Accounts owns the person's standing to act**: the claim that a past transaction was
theirs, the saved lists they keep for themselves, and the privacy request — the case, its lawful
basis, its participants, its evidence and its completion — that other modules execute against their
own rows.

| Neighbour | Owns | We own |
| --- | --- | --- |
| Identity | The login, the password, 2FA | The claim that links a login to a past guest transaction |
| Commerce Customers | The merchant's file: profile, addresses, groups, preferences, consent | Nothing of it. We ask it to erase and to export its own rows |
| Orders | The order | The claim, and the read seam that lists a person's orders |
| Returns | The return | Nothing. "My returns" is a read through the host |
| Reviews and Ratings | The review, the rating | Nothing. It publishes `EraseAuthor` and `AuthorExport` |
| Promotions | Offers, redemptions | Nothing. It publishes `RedactCustomerFromRedemptions` |
| Attribution and Analytics | Events, sessions, segments, measurement consent | Nothing. It publishes `RedactPerson` and `ExportPersonRecord` |
| Payment Operations | Stored payment methods and their references | Nothing |

## Three aggregates

### The privacy request

A **case**, not a function call. It records the subject, the kind (access or erasure), the lawful
basis, who asked, when they asked, when we heard, the deadline, its state, and **one row per
participant** recording that participant's outcome.

Participant rows are created **when the case is opened**, before anybody has been asked anything.
This is the whole mechanism: a case that grew its participant list as answers arrived could never
be partial, because the modules that did not answer would never have had a row to be missing from.

```
Open ──answer──▶ InProgress ──every participant completed──▶ Completed
  │                   │
  │                   └──conclude──▶ Partial   (outstanding participants named)
  └──withdraw──────────────────────▶ Withdrawn
```

`Completed` is written in exactly one place, `CaseProgression`, and requires **every** registered
participant to have completed. There is no operator override, because an override is the button
somebody presses to make a report look finished.

A case with **no** registered participants can never be satisfied. Nothing was asked, so nothing
may be presented as everything.

### The claim

**Evidence, not a permission.** A claim records what was presented (an order reference and the
address it was placed with), when, from what channel, and what it entitled the claimant to. "This
person may see order X" is *derived* from a granted claim every time it is asked — there is no
grant table, because a permission row outlives the reason for it and evidence does not.

```
                       ┌── refused (no row created) ─────────────────┐
present reference+address ──matched──▶ Pending ──proof──▶ Granted    │
                                          │                          │
                                          ├──late──▶ Expired         │
                                          └──erasure──▶ Revoked ─────┘
```

Proof of possession is the point. A reference and an address both appear on an emailed receipt, and
a receipt gets forwarded; possession of the mailbox is the thing that is hard to forward. The
module publishes the lifecycle and **not the mail** — it has no notification, no mailable and no
address book, and the address is held only as a one-way fingerprint.

**Failed attempts are recorded and are the point of the attempt log.** A module that stores only
successful claims cannot show an operator that somebody tried forty references in an hour. The
limit is counted two ways — per order reference and per address fingerprint — because either alone
is sidestepped by varying the other, and it lives in the domain rather than in one surface's
middleware so no surface can forget it.

### The saved list

Per person, per merchant, holding **product references and quantities and never a price**. The
price at the moment something was saved is not a fact this module may assert, and the price now
belongs to Catalog. A list is not a cart: a list survives a repricing, a cart does not.

**A share is its own aggregate**, belonging to the list. Several may exist at once, each with its
own token and its own revocation. One token per person means revoking the link you sent your sister
also revokes the one you sent your colleague, so nobody ever revokes anything.

## Scope is a field, not a convention

The fleet genuinely disagrees about what "erase me" means. Commerce Customers erases everywhere
because a person is a person; Attribution and Analytics erases within one merchant because a global
erasure would tell merchant A that merchant B exists. Both are right.

So a request **states** its scope, and every participant answer **records the scope it applied**.
When they differ, that is a stored mismatch — in either direction. A participant that reached
further than the case asked and one that reached less far are both facts an operator must see, and
reconciling them silently is how a fleet with two scopes pretends to have one.

## Units and shapes

- **`person_ref`** is the fleet's opaque subject reference. Never an integer, never an email, never
  a login id. Waves 13 and 14 use the same vocabulary.
- **Two times.** `requested_at` — when the person asked, which may be a letter dated last Tuesday —
  and `recorded_at`. A regulatory clock runs from the first and a support conversation from the
  second.
- **A deadline is a date and a timezone.** "Within one month" is a calendar answer; stored as an
  instant, the same deadline is a different day for a merchant in Auckland than for one in Los
  Angeles.
- **Identifiers are opaque strings and there are no foreign keys out of this module.** No `join`,
  no `DB::table`, anywhere in `src/`; the boundary suite asserts it.
- **Money never appears.** No column, no field, no computation. An order total comes from Orders
  through a seam, already shaped.
- **References are minted from `random_bytes`,** not from a ULID: ULIDs need `symfony/uid`, which
  `illuminate/support` does not require. They are also not sortable, so a public reference does not
  tell its holder how many cases were opened before theirs.

## Append-only, and where the hole is

`customer_accounts_claim_attempts` is append-only, guarded in model hooks. **Model hooks do not
fire for `query()->update()` or `query()->delete()`** — a hole this fleet has now found five times
— so the guarantee is stated twice: nothing in this package writes attempts through the builder,
and the boundary suite asserts that no source file does.

## Erasing our own rows

This module holds personal data of its own and is **not** a participant in its own registry: a
coordinator that appeared in its own registry could report its own erasure as one more green tick,
and would stop erasing itself the moment somebody edited the entry out. Concluding an erasure case
clears our rows directly, on a partial conclusion too.

- **Shares are revoked**, explicitly, because the application this replaced forgot to. Its erasure
  scrubbed the name, the email, the verification, the password, the remember token and both 2FA
  columns, and left `wishlist_share_token` intact — so a URL published to third parties still
  resolved to the erased user's row. A live token pointing at an erased subject is the failure that
  outlives the request.
- **Lists and items are deleted.** They are the person's own saved data; there is no shape worth
  keeping.
- **Claims are redacted, not deleted.** A claim is the record of somebody having been let into an
  order, and an unexplained grant is a worse audit outcome than a redacted one.
- **Attempts are untouched.** They carry no subject reference — only a one-way fingerprint of an
  address typed at a form — and they are the only evidence that anybody tried to enumerate a
  merchant's orders.

## What is deliberately not here

- **No preferences store.** Wave 13 shipped typed preferences with per-channel consent on the
  customer file. Building a second one would fork the definition of consent.
- **No payment-method references.** Holding a copy of a provider reference is holding the
  reference.
- **No saved carts, no gift registries.** Different aggregates; see `docs/adoption.md` §5.
- **No seam for returns, reviews or payment methods.** A seam per neighbour is how this module
  becomes a façade over the whole fleet.
- **No password, no token, no second factor** is read or written. Re-authentication before a
  destructive request is the host's to perform; we record *that* it was performed and by what
  means, and we refuse to open an erasure that cannot say.
