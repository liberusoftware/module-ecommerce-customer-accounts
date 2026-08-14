<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every attempt, including — especially — the ones that failed.
 *
 * A module that records only successful claims cannot show an operator that
 * somebody tried forty references in an hour, and that is the only signal there
 * is that a claim endpoint is being used as an order-reference oracle. The
 * failures are the security record; the successes are the audit trail.
 *
 * Separate from the claims table because a failed attempt has no claim: a guess
 * at a reference that does not exist must not create a row keyed by it, or the
 * table becomes the oracle.
 *
 * Append-only. Rows are never updated and never deleted, and the model enforces
 * it — including for a rate limit that would otherwise be tempted to "reset".
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_claim_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('order_reference', 64);
            $table->string('email_fingerprint', 64);
            $table->string('claim_reference', 64)->nullable();
            $table->string('outcome', 32);
            $table->string('channel', 32);
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'order_reference', 'attempted_at']);
            $table->index(['tenant_id', 'email_fingerprint', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_claim_attempts');
    }
};
