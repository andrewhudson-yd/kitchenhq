<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_checks', fn (Blueprint $t) => $t->string('quantity', 30)->nullable());
    }

    public function down(): void
    {
        Schema::table('shopping_checks', fn (Blueprint $t) => $t->dropColumn('quantity'));
    }
};
