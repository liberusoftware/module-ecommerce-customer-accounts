<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A saved list: per person, per merchant, holding product references.
 *
 * Not a saved cart. A list holds references and survives a repricing; a cart
 * holds priced lines and does not. The epic conflates them; they are different
 * aggregates and this module builds only the first.
 *
 * The host's `wishlists` table is not adopted. Its lookup key was
 * `firstOrCreate(['user_id', 'product_id'])` under a store scope that applies no
 * predicate at all when no store is in context — so off a storefront the lookup
 * matched any merchant's row and the add silently resolved into somebody else's
 * wishlist. `tenant_id` is part of the identity here rather than a scope applied
 * to it.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_saved_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('tenant_id', 64);
            $table->string('owner_ref', 64);
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'owner_ref', 'name']);
            $table->index(['tenant_id', 'owner_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_saved_lists');
    }
};
