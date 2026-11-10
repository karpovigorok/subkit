<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Top-up is a provider-neutral product (name, display amount). Which payment provider can sell it,
 * and under which provider reference, lives in subkit_top_up_provider_prices, mirroring Plans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subkit_top_up_provider_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('top_up_id')->constrained('subkit_top_ups')->cascadeOnDelete();
            $table->string('provider', 50);           // stripe | paypal | …
            $table->string('provider_price_id', 255); // PSP-owned reference
            $table->timestamps();

            $table->unique(['top_up_id', 'provider']);
        });

        Schema::table('subkit_top_ups', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('amount')->nullable()->after('description'); // display amount, minor units
            $table->string('currency', 3)->nullable()->after('amount');           // null = configured default
        });

        foreach (DB::table('subkit_top_ups')->get(['id', 'provider', 'provider_price_id']) as $row) {
            DB::table('subkit_top_up_provider_prices')->insert([
                'top_up_id' => $row->id,
                'provider' => $row->provider,
                'provider_price_id' => $row->provider_price_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('subkit_top_ups', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_price_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subkit_top_ups', function (Blueprint $table) {
            $table->string('provider', 50)->default('stripe');
            $table->string('provider_price_id', 255)->default('');
        });

        foreach (DB::table('subkit_top_up_provider_prices')->orderBy('id')->get() as $price) {
            // The old shape holds one provider per top-up: keep the first price of each.
            DB::table('subkit_top_ups')
                ->where('id', $price->top_up_id)
                ->where('provider_price_id', '')
                ->update(['provider' => $price->provider, 'provider_price_id' => $price->provider_price_id]);
        }

        Schema::dropIfExists('subkit_top_up_provider_prices');

        Schema::table('subkit_top_ups', function (Blueprint $table) {
            $table->dropColumn(['description', 'amount', 'currency']);
        });
    }
};
