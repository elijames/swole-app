<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('exercise_id')->unique(); // API's exerciseId
            $table->string('name');
            $table->string('gif_url');
            $table->json('target_muscles');
            $table->json('body_parts');
            $table->json('equipments');
            $table->json('secondary_muscles');
            $table->json('instructions');
            $table->integer('category')->default(1); // 1: Strength, 2: Bodyweight, 3: Cardio
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};