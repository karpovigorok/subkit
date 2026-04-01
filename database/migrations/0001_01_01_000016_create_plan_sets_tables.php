<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subkit_plan_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();   // used in <x-subkit::pricing-table set="code" />
            $table->text('description')->nullable();
            $table->string('success_url', 500)->nullable();
            $table->string('cancel_url',  500)->nullable();
            $table->string('free_url',    500)->nullable();
            $table->string('guest_url',   500)->nullable();
            $table->string('subscribe_label', 100)->nullable();
            $table->string('free_label',      100)->nullable();
            $table->string('guest_label',     100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('theme', 50)->default('default');
            $table->timestamps();
        });

        Schema::create('subkit_plan_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_set_id')->constrained('subkit_plan_sets')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subkit_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_highlighted')->default(false);
            $table->timestamps();

            $table->unique(['plan_set_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subkit_plan_set_items');
        Schema::dropIfExists('subkit_plan_sets');
    }
};
