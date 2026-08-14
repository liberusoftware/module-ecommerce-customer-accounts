# Adopting `ecommerce-customer-accounts`

## 1. The repository entry

The package is not on Packagist. Add it to the **host's own** `composer.json` — Composer honours
`repositories` only from the root manifest, so a VCS entry inside a dependency does nothing:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-customer-accounts" }
]
```

```bash
composer require liberusoftware/ecommerce-customer-accounts:^0.1
php artisan migrate
```

Then name the module in `MODULES_ENABLED`. Composer boots nothing: the package ships no
`extra.laravel.providers`, and the host's module manager registers
`CustomerAccountsServiceProvider` only when the module is enabled.

```bash
php artisan vendor:publish --tag=customer-accounts-config
```

Publishing the config is **not optional**, because the participant registry lives in it and an
empty registry means every case is partial. That is the correct behaviour and a bad steady state.

## 2. The participant registry

```php
// config/customer-accounts.php
'participants' => [
    'commerce-customers' => [
        'label'   => 'Customer files',
        'adapter' => \App\Privacy\CommerceCustomersParticipant::class,
        'handles' => ['access', 'erasure'],
    ],
    'reviews-and-ratings' => [
        'label'   => 'Reviews and ratings',
        'adapter' => \App\Privacy\ReviewsParticipant::class,
        'handles' => ['access', 'erasure'],
    ],
    'promotions' => [
        'label'   => 'Promotions',
        'adapter' => \App\Privacy\PromotionsParticipant::class,
        'handles' => ['erasure'],   // it publishes no export at all
    ],
],
```

Three rules, and each of them exists because of a specific way this goes wrong:

- **A participant that is not registered is invisible**, and an invisible participant is exactly
  how a module's rows survive an erasure that reported success. There is no discovery, no scan and
  no convention. Registering a new module is **part of installing it** — put it in the same pull
  request as the `composer require`.
- **A registered participant with no adapter, or an adapter nothing can resolve, answers
  "unavailable"** and makes the case partial. This is a feature: a module you have not written an
  adapter for yet is *visible* in the desk rather than absent from the concept of the case.
- **`handles` is read pessimistically.** An entry that declares nothing publishes nothing. A
  participant that does not publish the half being asked for answers unavailable immediately,
  which is what makes an export honestly partial rather than quietly short.

## 3. Writing an adapter

You write one class per participating module. This package calls exactly one method on it and
knows nothing about the four verbs, four return types, three subject names and two scopes behind
it. Below are worked adapters for the **two most different** signatures in the fleet.

### 3.1 Commerce Customers — cross-tenant, `array` return

`RedactPerson::__invoke(string $personRef, string $reason, string $actorRef, ?CarbonImmutable $occurredAt): array`

It takes **no tenant**, erases the person from every merchant file it holds, and returns the ids it
redacted. It is the only cross-tenant erasure in the fleet, deliberately: a person is a person.

```php
namespace App\Privacy;

use Liberu\Ecommerce\CommerceCustomers\Actions\ExportPersonFile;
use Liberu\Ecommerce\CommerceCustomers\Actions\RedactPerson;
use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

final readonly class CommerceCustomersParticipant implements ParticipatesInPrivacyRequests
{
    public function __construct(
        private RedactPerson $redact,
        private ExportPersonFile $export,
    ) {}

    public function participate(ParticipationRequest $request): ParticipationAnswer
    {
        if ($request->kind === RequestKind::Access) {
            return ParticipationAnswer::completed(
                // Its export is per person, not per merchant, so it too answers
                // at Everywhere whatever was asked.
                RequestScope::Everywhere,
                summary: [],
                payload: ($this->export)($request->subjectRef),
            );
        }

        $ids = ($this->redact)(
            personRef: $request->subjectRef,          // its name for the subject
            reason: $request->reason ?? 'subject request',
            actorRef: $request->actorRef ?? 'system',
        );

        return ParticipationAnswer::completed(
            // THE IMPORTANT LINE. It says what it *did*, not what was asked.
            // A tenant-scoped case answered here is recorded as a mismatch,
            // because this module erased more than the case asked for and an
            // operator has to be able to see that.
            RequestScope::Everywhere,
            summary: ['files_redacted' => count($ids)],
        );
    }
}
```

### 3.2 Reviews and Ratings — per tenant, `ErasureReport` return, export is a *query*

`EraseAuthor::__invoke(string $tenantId, string $authorReference): ErasureReport`

Tenant first, a third name for the subject (`authorReference`), and an object return whose fields
are counts. Its export is a query class rather than an action.

```php
namespace App\Privacy;

use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\EraseAuthor;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\AuthorExport;

final readonly class ReviewsParticipant implements ParticipatesInPrivacyRequests
{
    public function __construct(
        private EraseAuthor $erase,
        private AuthorExport $export,
    ) {}

    public function participate(ParticipationRequest $request): ParticipationAnswer
    {
        if ($request->kind === RequestKind::Access) {
            return ParticipationAnswer::completed(
                RequestScope::Tenant,
                payload: ['reviews' => ($this->export)($request->tenantId, $request->subjectRef)],
            );
        }

        $report = ($this->erase)($request->tenantId, $request->subjectRef);

        return ParticipationAnswer::completed(
            // It can only answer for one merchant. Asked to erase everywhere,
            // this is a recorded mismatch — see §3.3 for what to do about it.
            RequestScope::Tenant,
            summary: [
                'expressions' => $report->expressions,
                'votes' => $report->votes,
                'flags' => $report->flags,
            ],
        );
    }
}
```

### 3.3 The scope split, and what an adapter may and may not do about it

The two adapters above are the two ends of the problem: one can only answer everywhere, the other
only per merchant. **Neither is wrong and neither may lie**, so:

- Report the scope you **applied**, never the scope you were asked for. `scopeApplied` is a fact
  about your implementation; the mismatch is computed here and shown in the desk.
- If your module is per-tenant and the case asked for `Everywhere`, you have two honest options:
  answer at `Tenant` and let the mismatch be recorded, or loop the deployment's merchants (the
  host is the only thing that knows them all) and answer at `Everywhere`. Do **not** loop silently
  and then report `Tenant`, and do not report `Everywhere` for one merchant's worth of work.
- Never throw to mean "unavailable". Return `ParticipationAnswer::unavailable(...)`. A throw is
  recorded as `Failed`, which is a different fact — *something was attempted and broke* — and it
  is retried on the assumption that it might work next time.

### 3.4 The six modules that publish nothing

**Orders, Returns, Payment Operations, Shipping, Tax and Gift Cards publish no erasure and no
export at all.** Every one of them holds personal data. Until they do publish something, you have
three options and only two of them are honest:

1. **Register them with no adapter.** Every case naming them is partial and the desk shows exactly
   which modules are silent. This is the recommended state — it is a visible, countable backlog.
2. **Write an adapter that reaches into their tables from the host.** Legitimate, since the host
   may do things a module may not; put it in `App\Privacy\` and treat it as debt.
3. **Leave them out of the registry.** Do not do this. It produces cases that report Completed
   over rows nobody touched, which is the defect this module was extracted to end.

## 4. What a partial completion means operationally

`RequestState::Partial` means: **the case is finished and somebody's data was not dealt with.** It
is not a warning, a soft failure or a transient state.

- The person must be told in those terms. `RequestProgress::$outstanding` names the participants,
  and `PrivacyRequestConcluded` carries the progress so a notification cannot be written without
  it. "Your data has been erased" is only ever true of a `Completed` case.
- For an **access** request, `AssembleExport` returns an artefact with `complete: false` and
  `missing_participants` populated. Deliver it, labelled partial, with those names on it.
- For an **erasure**, the subject's rights are not satisfied. Whether that is a reportable breach,
  a regulator notification or an ordinary backlog item is a legal decision, not a technical one —
  the module's job is to make it impossible not to know.
- A partial case can be **retried into completeness only by opening a new case**. A concluded case
  is closed; `CommissionParticipant` refuses it. That is deliberate: reopening would mean a case
  whose reported state changed after somebody was told about it.
- This module's **own** rows are cleared on any erasure conclusion, partial included.

## 5. What this module does not build

- **Preferences and communication consent** — Commerce Customers (`#840`). Settled in wave 13; do
  not build a second preferences store.
- **Payment-method references** — Payment Operations. A stored instrument and its provider
  reference are payment's, and a second copy of a reference is holding the reference.
- **Returns and order history** — the owning modules. `ResolvesOrderHistory` is a read seam, and
  there is deliberately **no seam for returns, reviews or payment methods**: a seam per neighbour
  is how this module becomes a façade over the whole fleet.
- **Saved carts** — Cart, unbuilt. A saved list holds product references and survives a repricing;
  a cart holds priced lines and does not. We build saved lists.
- **Gift registries** — still in the host, and owned by nobody. A registry is a shared,
  third-party-purchasable list with shipping and an access code, which is a different aggregate
  from a private saved list. Recorded as unowned rather than absorbed to make the module look
  bigger.

## 6. Host migrations to delete

This module adopts **no** host table; every table it owns is new and prefixed. Once the host's
account surfaces are moved onto it:

- `wishlists` and `users.wishlist_share_token` are replaced by
  `customer_accounts_saved_lists` / `_items` / `_shares`. The share token moves off the person and
  onto the list, which is what fixes one link answering differently per storefront.
- `App\Services\GdprErasureService` and `App\Services\GdprExportService` are replaced by cases and
  participants. Delete the services rather than calling them from an adapter: the erasure writes
  ten tables belonging to six modules, four of which now publish their own erasure, and calling it
  from a participant would erase those rows twice and the others never.
- `App\Http\Controllers\AccountErasureController` performed the re-authentication and left no
  trace. Keep the password check — it was the right control — and pass its name to
  `reauthenticatedVia`.

## 7. Verifying the install

```php
use Liberu\Ecommerce\CustomerAccounts\Queries\ListParticipants;

collect((new ListParticipants())())->filter(fn ($p) => $p->isSilent())->pluck('name');
```

An empty result is the goal. Anything in it is a module whose rows will survive your next erasure,
named in advance rather than discovered afterwards.
