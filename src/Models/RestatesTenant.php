<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Every relation in this module restates its parent's tenant.
 *
 * Tenancy leaks through **relations** rather than queries: a domain whose every
 * query states its tenant never exercises its own relations, and the leak
 * arrives through the one path nobody wrote a `where` on. So the restatement is
 * defence in depth on top of the foreign key — and for `OrderClaim::attempts()`
 * it is not defence in depth at all but the only thing there is, because that
 * relation joins on `order_reference` with no foreign key behind it.
 *
 * **And it is guarded, which is the part that is easy to get wrong.**
 * `withCount()` and `whereHas()` build the relation from a *fresh* instance whose
 * `tenant_id` is `null`. An unguarded `where('tenant_id', (string) null)` becomes
 * `where('tenant_id', '')` and matches nothing, so every `withCount` on this
 * module would silently report zero — the mirror image of the leak, and just as
 * wrong: a merchant reading `0 saved items` acts on it as confidently as on a
 * wrong number. (Written without the cast it is worse still: `where('col', null)`
 * compiles to `is null`, which on a nullable column lists exactly the orphan rows
 * a tenancy scope is there to hide. Every `tenant_id` in this module's migrations
 * is NOT NULL for that reason.)
 *
 * Guarded, the constraint applies when there is a tenant to apply and the
 * relation falls back to the foreign key when there is not — and the foreign key
 * is itself tenant-safe, because every child points at a parent that belongs to
 * exactly one merchant.
 *
 * **`OrderClaim::attempts()` is the one relation that fallback does not cover**,
 * and it is the one where the leak would matter most: with no foreign key, the
 * join is on a reference two merchants share, so a fresh instance falling back to
 * it counts both. That relation passes `correlate: true`, which restates the
 * tenant as a column comparison against the parent row instead of a value — right
 * in the correlated subquery `withCount()` and `whereHas()` build, and never
 * reached through a loaded parent, where the parent's table is not in the query.
 *
 * Both halves are asserted in `CustodyTest`, and `ModuleBoundaryRulesTest` fails
 * if a model restates a tenant unguarded again.
 */
trait RestatesTenant
{
    /**
     * @template TRelation of Relation
     *
     * @param  TRelation  $relation
     * @param  bool  $correlate  Correlate the child's tenant to the parent row's own
     *                           `tenant_id` column when there is no loaded tenant to
     *                           state. Needed only where the fallback described above
     *                           does not hold — a relation with no foreign key behind
     *                           it, where falling back to the join alone would count
     *                           every merchant's rows.
     * @return TRelation
     */
    protected function scopedToTenant(Relation $relation, bool $correlate = false): Relation
    {
        $tenant = $this->getAttribute('tenant_id');
        $childColumn = $relation->getRelated()->qualifyColumn('tenant_id');

        if (is_string($tenant) && $tenant !== '') {
            $relation->getQuery()->where($childColumn, $tenant);
        } elseif ($correlate) {
            $relation->getQuery()->whereColumn($childColumn, $this->qualifyColumn('tenant_id'));
        }

        return $relation;
    }
}
