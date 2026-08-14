<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A product reference and a quantity. There is no price column and there will
 * not be one: the price at the moment something was saved is not a fact this
 * module may assert, and the price now belongs to Catalog.
 *
 * `tenant_id` is carried in its own right rather than inherited through the
 * parent, so a query that forgets the join still cannot cross a merchant.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts_saved_list_items', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->unsignedBigInteger('saved_list_id');
            $table->string('product_ref', 64);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('note')->nullable();
            $table->timestamp('added_at');
            $table->timestamps();

            $table->unique(['saved_list_id', 'product_ref']);
            $table->index(['tenant_id', 'product_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts_saved_list_items');
    }
};
