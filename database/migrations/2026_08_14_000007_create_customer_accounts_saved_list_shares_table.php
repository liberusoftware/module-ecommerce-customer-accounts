<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A share is its own aggregate, belonging to the **list**.
 *
 * The host put one `wishlist_share_token` on `users`, with a unique index, while
 * the wishlist itself was store-scoped and the shared route was public and
 * unauthenticated. So the store came from whichever host the visitor landed on,
 * and one link showed a different slice per storefront with nothing on the page
 * saying anything was missing. One token, many answers.
 *
 * Here a token points at one list at one merchant, several may exist at once,
 * each is revocable on its own, and revoking is an act with a time and a reason
 * rather than a null.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_saved_list_shares', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('tenant_id', 64);
            $table->unsignedBigInteger('saved_list_id');
            $table->string('token_fingerprint', 64)->unique();
            $table->string('owner_ref', 64);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'saved_list_id']);
            $table->index(['tenant_id', 'owner_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_saved_list_shares');
    }
};
