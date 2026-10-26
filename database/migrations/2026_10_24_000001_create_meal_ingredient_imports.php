<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_ingredient_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->date('week');
            $table->string('fingerprint', 64);
            $table->string('source_name', 180);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['household_id', 'fingerprint']);
            $table->index(['household_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_ingredient_imports');
    }
};
