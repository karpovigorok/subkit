<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subkit_plan_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('key');
            $table->string('value');
            $table->string('type')->default('string');
            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('subkit_plans')
                ->cascadeOnDelete();

            $table->unique(['plan_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subkit_plan_limits');
    }
};
