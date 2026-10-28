<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->boolean('is_default_purchase')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_units', fn (Blueprint $t) => $t->dropColumn('is_default_purchase'));
    }
};
