<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_items', fn (Blueprint $t) => $t->string('qualitative_stock')->nullable());
        Schema::create('household_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('source_key');
            $t->json('payload');
            $t->timestamps();
            $t->unique(['household_id', 'source_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('household_imports');
        Schema::table('inventory_items', fn (Blueprint $t) => $t->dropColumn('qualitative_stock'));
    }
};
