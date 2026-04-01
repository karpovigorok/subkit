<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subkit_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('interval', 20);  // monthly | yearly
            $table->unsignedSmallInteger('trial_days')->nullable();
            // Stored in cents to avoid floating point issues: 999 = $9.99, 1000 = $10.00
            $table->unsignedInteger('price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subkit_plans');
    }
};
