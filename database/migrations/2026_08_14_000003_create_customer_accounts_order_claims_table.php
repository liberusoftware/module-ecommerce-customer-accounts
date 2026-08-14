<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The claim: the evidence that a past guest transaction belongs to the person
 * holding this row. Nobody in the host owned this idea, so a guest order was
 * invisible forever — checkout wrote `user_id = auth()->id()`, which is null for
 * a guest, and the order list scopes on `user_id`.
 *
 * It stores evidence, not a permission. "This person may see order X" is derived
 * from a granted claim; there is no grant table and no ACL row, because a
 * permission outlives the reason for it and evidence does not.
 *
 * The address is stored **only** as a fingerprint. It is enough to match, to
 * count and to rate-limit, and it does not build a pile of addresses that people
 * typed at a claim form.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_order_claims', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('tenant_id', 64);
            $table->string('order_reference', 64);
            // Nullable because erasure redacts rather than deletes: the claim,
            // its outcome and its times are an audit trail of who was let into
            // an order, and destroying it to satisfy an erasure would leave the
            // grant unexplained. The person goes, the shape stays.
            $table->string('claimant_ref', 64)->nullable();
            $table->string('email_fingerprint', 64)->nullable();
            $table->string('state', 32);
            $table->string('channel', 32);
            // The proof-of-possession secret, fingerprinted. A token readable out
            // of the database is a token an operator can use.
            $table->string('token_fingerprint', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('recorded_at');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'order_reference']);
            $table->index(['tenant_id', 'claimant_ref', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_order_claims');
    }
};
