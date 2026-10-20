<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopping_photo_drafts', function (Blueprint $t) {
            $t->string('purpose')->default('list');
            $t->string('content_hash', 64)->nullable()->index();
            $t->unique(['household_id', 'purpose', 'content_hash'], 'receipt_image_per_household');
        });
        Schema::table('shopping_checks', fn (Blueprint $t) => $t->timestamp('stocked_at')->nullable());
        Schema::create('purchase_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shopping_photo_draft_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->date('week');
            $t->json('rows');
            $t->json('result')->nullable();
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
        });
        Schema::create('receipt_item_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('alias_key');
            $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $t->unique(['household_id', 'alias_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('receipt_item_aliases');
        Schema::dropIfExists('purchase_batches');
        Schema::table('shopping_checks', fn (Blueprint $t) => $t->dropColumn('stocked_at'));
        Schema::table('shopping_photo_drafts', function (Blueprint $t) {
            $t->dropUnique('receipt_image_per_household');
            $t->dropIndex('shopping_photo_drafts_content_hash_index');
            $t->dropColumn(['purpose', 'content_hash']);
        });
    }
};
