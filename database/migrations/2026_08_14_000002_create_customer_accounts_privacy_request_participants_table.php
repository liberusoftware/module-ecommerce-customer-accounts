<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per participant per case, created when the case is opened and before
 * anybody has been asked anything.
 *
 * Creating them up front is the whole mechanism. A case that grew its
 * participant list as answers arrived could never be partial, because the
 * modules that did not answer would never have had a row — which is precisely
 * how the host's erasure reported success over six modules it had never heard
 * of.
 *
 * `scope_applied` is what the participant says it actually did, and
 * `scope_mismatch` is that against what the case asked for. Commerce Customers
 * can only erase everywhere; Reviews can only erase per tenant. Neither is
 * wrong, and reconciling them silently would be.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_privacy_request_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->unsignedBigInteger('privacy_request_id');
            $table->string('participant', 64);
            $table->string('label');
            $table->string('outcome', 32);
            $table->string('scope_applied', 32)->nullable();
            $table->boolean('scope_mismatch')->default(false);
            $table->json('summary')->nullable();
            // The export contribution. Null for an erasure, and null until the
            // participant answers.
            $table->json('payload')->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['privacy_request_id', 'participant']);
            $table->index(['tenant_id', 'participant']);
            $table->index(['privacy_request_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_privacy_request_participants');
    }
};
