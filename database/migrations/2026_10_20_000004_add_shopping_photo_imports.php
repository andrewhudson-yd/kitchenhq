<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopping_photo_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->date('week');
            $t->string('path');
            $t->string('mime', 30);
            $t->json('items')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
        });
        Schema::create('shopping_extras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->date('week');
            $t->string('item_key', 64);
            $t->string('name', 150);
            $t->string('source')->default('manual');
            $t->timestamps();
            $t->unique(['household_id', 'week', 'item_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('shopping_extras');
        Schema::dropIfExists('shopping_photo_drafts');
    }
};
