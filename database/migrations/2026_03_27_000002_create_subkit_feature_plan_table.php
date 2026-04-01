<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subkit_feature_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subkit_plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('subkit_features')->cascadeOnDelete();
            $table->string('value', 100)->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subkit_feature_plan');
    }
};
