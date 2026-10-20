<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('households', function (Blueprint $t) {
            $t->date('plan_starts_on')->nullable();
            $t->text('ai_api_key')->nullable();
            $t->string('ai_model')->default('gpt-4.1-mini');
        });
        Schema::table('recipes', fn (Blueprint $t) => $t->json('shopping_ingredients')->nullable());
        Schema::create('shopping_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->date('week');
            $t->string('item_key', 64);
            $t->boolean('checked')->default(false);
            $t->unique(['household_id', 'week', 'item_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('shopping_checks');
        Schema::table('recipes', fn (Blueprint $t) => $t->dropColumn('shopping_ingredients'));
        Schema::table('households', fn (Blueprint $t) => $t->dropColumn(['plan_starts_on', 'ai_api_key', 'ai_model']));
    }
};
