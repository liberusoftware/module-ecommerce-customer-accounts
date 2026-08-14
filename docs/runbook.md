# Runbook — running a privacy desk

## Daily: who is silent?

```php
use Liberu\Ecommerce\CustomerAccounts\Queries\ListParticipants;

collect((new ListParticipants())())->filter(fn ($p) => $p->isSilent())->pluck('name');
```

A silent participant is a module whose rows will survive the next erasure. Empty is the goal;
anything in the list is a named backlog item rather than a surprise. **Registering a module is part
of installing it** — a `composer require` of a module that holds personal data and no matching
registry entry is an incident waiting for a subject access request.

## A case is stuck in `InProgress`

Look at the participant rows. There are exactly four things a participant can be:

| Outcome | Means | Do |
| --- | --- | --- |
| `Pending` | never asked | commission it |
| `Completed` | answered, done | nothing; it cannot be re-commissioned |
| `Unavailable` | nothing was attempted — unbound, deregistered, or publishes no such half | bind an adapter, or accept a partial |
| `Failed` | something was attempted and threw; `note` carries the class and message | fix and retry |

```php
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;

(new CommissionParticipant())($reference, 'reviews-and-ratings');
```

Retrying is safe for `Pending`, `Unavailable` and `Failed`, and refused for `Completed`. `attempts`
counts every try, so a participant on its ninth attempt is visible without reading logs.

## The deadline is here and somebody has not answered

```php
use Liberu\Ecommerce\CustomerAccounts\Actions\ConcludePrivacyRequest;

(new ConcludePrivacyRequest())($reference);   // Partial, with the names on it
```

This cannot produce `Completed` unless every participant already completed. **That is not a
limitation to work around.** If you find yourself wanting to force it, what you actually want is
either an adapter for the silent module or an honest partial with the person told.

A concluded case is closed and cannot be reopened. To finish the work after fixing an adapter, open
a **new** case: a case whose reported state changes after somebody was told about it is worse than
two cases.

## Deciding whether a case is late

```php
$view = (new FindPrivacyRequest())($reference);
$view->deadline;                                    // date + IANA timezone
$request->deadline()->hasPassed(CarbonImmutable::now());
```

Late means the day is over **where the deadline is counted**. Do not compare the stored date to a
UTC instant by hand; for a merchant twelve hours off UTC that is wrong for twelve hours a day, in
whichever direction they sit.

## Somebody is enumerating order references

```php
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;

ClaimAttempt::query()
    ->where('tenant_id', $tenant)
    ->where('attempted_at', '>=', now()->subDay())
    ->get()
    ->groupBy('email_fingerprint')
    ->map->count()
    ->sortDesc();
```

Attempts are append-only: they cannot be updated or deleted through the model, and nothing in the
package writes them through the query builder. If a count looks wrong, the answer is not to reset
it — tighten `customer-accounts.claim.max_attempts` and `attempt_window_minutes`, which are counted
per merchant against both the reference and the address.

The address is stored only as a SHA-256 fingerprint. To check a specific address, fingerprint it
and compare — there is no way to read one back, on purpose.

## The claim mail is not arriving

The module does not send it. `GuestOrderClaimOpened` is the event; the host mails
`ClaimProof::$token` **to the address on the order**, which is the whole proof of possession. The
token is on the return value and deliberately not on the event, because an event is broadcast,
logged and serialised.

If claiming is refusing everything: check that something is bound to `VerifiesGuestOrderClaim`.
Unbound, claiming is *closed* and every attempt raises `ClaimVerificationUnavailable` with an
attempt recorded as `verifier_unavailable`.

## A person says their shared list link stopped working

Expected after an erasure — concluding an erasure case revokes every share the subject holds, and
deleting the token is the strongest revocation available. Before that, `RevokeShare` records a time
and a reason, and `ResolveShare` answers `null` for a revoked token and for a token that never
existed, identically.

## Things that are not incidents

- **A partial case.** It is the module working: somebody's data was not dealt with and you now know
  which somebody. See `docs/adoption.md` §4 for what to tell the person.
- **A scope mismatch.** Commerce Customers can only erase everywhere and Reviews only per merchant.
  A mismatch is a recorded fact about which of them did what, not a failure.
- **Every case partial after installing a new module.** That is the registry doing its job before
  the adapter exists.
