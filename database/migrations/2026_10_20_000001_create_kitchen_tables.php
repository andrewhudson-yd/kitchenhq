<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('join_code', 64)->unique();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('household_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('family_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedTinyInteger('age')->nullable();
            $t->text('likes')->nullable();
            $t->text('dislikes')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('storage_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('storage_location_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->decimal('quantity', 12, 3)->nullable();
            $t->string('unit', 20);
            $t->decimal('minimum_quantity', 12, 3)->default(0);
            $t->date('expires_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['household_id', 'name']);
        });
        Schema::create('recipe_kits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('brand')->default('SimplyCook');
            $t->unsignedInteger('quantity')->nullable();
            $t->timestamps();
            $t->unique(['household_id', 'name', 'brand']);
        });
        Schema::create('recipes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('recipe_kit_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->unsignedSmallInteger('servings')->default(1);
            $t->boolean('is_simplycook')->default(false);
            $t->boolean('is_roast')->default(false);
            $t->boolean('planning_only')->default(false);
            $t->text('instructions')->nullable();
            $t->timestamps();
        });
        Schema::create('recipe_ingredients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->decimal('quantity', 12, 3);
            $t->string('unit', 20);
            $t->timestamps();
        });
        Schema::create('planned_meals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('recipe_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->string('slot');
            $t->string('audience');
            $t->unsignedSmallInteger('servings');
            $t->timestamp('cooked_at')->nullable();
            $t->timestamps();
            $t->unique(['household_id', 'date', 'slot', 'audience']);
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('planned_meal_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('change', 12, 3);
            $t->string('reason');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_movements', 'planned_meals', 'recipe_ingredients', 'recipes', 'recipe_kits', 'inventory_items', 'storage_locations', 'family_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('household_id'));
        Schema::dropIfExists('households');
    }
};
