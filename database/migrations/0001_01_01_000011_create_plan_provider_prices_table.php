<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subkit_plan_provider_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subkit_plans')->cascadeOnDelete();
            $table->string('provider', 50);           // stripe | paypal | …
            $table->string('provider_price_id', 255); // PSP-owned price identifier
            $table->timestamps();

            $table->unique(['plan_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subkit_plan_provider_prices');
    }
};
