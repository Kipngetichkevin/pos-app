<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('selling_price');
            // Buy at least this many (base units) to get the wholesale price; null = wholesale customers only
            $table->decimal('wholesale_min_qty', 12, 3)->nullable()->after('wholesale_price');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('price_level', ['retail', 'wholesale'])->default('retail')->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'wholesale_min_qty']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('price_level');
        });
    }
};
