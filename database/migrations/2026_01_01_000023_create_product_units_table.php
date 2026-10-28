<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->decimal('quantity', 12, 3);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->string('barcode', 50)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignUuid('product_unit_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('unit_name', 50)->nullable()->after('product_name');
            $table->decimal('base_quantity', 12, 3)->nullable()->after('quantity');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->string('unit_name', 50)->nullable()->after('product_name');
            $table->decimal('unit_quantity', 12, 3)->nullable()->after('unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', fn (Blueprint $t) => $t->dropColumn(['unit_name', 'unit_quantity']));
        Schema::table('sale_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('product_unit_id');
            $t->dropColumn(['unit_name', 'base_quantity']);
        });
        Schema::dropIfExists('product_units');
    }
};
