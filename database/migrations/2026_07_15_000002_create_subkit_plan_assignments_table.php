<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subkit_plan_assignments');

        Schema::create('subkit_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('subkit_plans')
                ->cascadeOnDelete();

            $table->index(['assignable_type', 'assignable_id']);

            $table->unique(['plan_id', 'assignable_type', 'assignable_id'], 'subkit_plan_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subkit_plan_assignments');
    }
};
