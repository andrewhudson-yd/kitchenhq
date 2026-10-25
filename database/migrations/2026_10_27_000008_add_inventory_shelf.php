<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A section the household picked for an item, overriding the automatic one.
        Schema::table('inventory_items', fn (Blueprint $t) => $t->string('shelf', 40)->nullable());
    }

    public function down(): void
    {
        Schema::table('inventory_items', fn (Blueprint $t) => $t->dropColumn('shelf'));
    }
};
