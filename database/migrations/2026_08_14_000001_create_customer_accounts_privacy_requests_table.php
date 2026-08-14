<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The case. The host had no such row at all: its erasure ran inside an HTTP
 * request, wrote ten tables belonging to six modules, and returned
 * {"success": true} from a function that could not have known — and a person
 * whose request failed halfway had no case number to ask about.
 *
 * `tenant_id` is the merchant the case was **raised at**, and it is always
 * present. `scope` says how far it reaches. This is the deliberate exception to
 * the rule that every row in this module belongs to one merchant: a request
 * whose scope is `everywhere` is a deployment-wide case that happens to have
 * been raised somewhere, and it is answered by participants that cross tenants.
 * The exception is tested rather than assumed — see the custody suite.
 *
 * `reauthenticated_via` is a name — "password", "identity documents at the desk"
 * — never material. No password, no token and no second factor is stored here.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('tenant_id', 64);
            $table->string('subject_ref', 64);
            $table->string('kind', 32);
            $table->string('scope', 32);
            $table->string('lawful_basis', 48);
            $table->string('state', 32);
            $table->string('channel', 32);
            $table->string('requested_by_ref', 64)->nullable();
            $table->string('reason')->nullable();
            $table->string('reauthenticated_via', 64)->nullable();
            // A date and a zone, never a bare instant: a statutory period is
            // counted in days in a jurisdiction.
            $table->date('deadline_date');
            $table->string('deadline_timezone', 64);
            // When the person asked; the regulatory clock runs from here.
            $table->timestamp('requested_at');
            // When we heard about it; the support conversation runs from here.
            $table->timestamp('recorded_at');
            $table->timestamp('concluded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'subject_ref']);
            $table->index(['tenant_id', 'state']);
            $table->index(['subject_ref', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_privacy_requests');
    }
};
