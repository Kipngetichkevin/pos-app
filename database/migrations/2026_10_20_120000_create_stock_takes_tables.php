<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('take_no', 30)->unique(); // ST-MAIN-261020-01
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete(); // null = whole shop
            $table->foreignUuid('user_id')->constrained(); // started by
            $table->foreignUuid('completed_by')->nullable()->constrained('users');
            $table->enum('status', ['counting', 'completed', 'cancelled'])->default('counting');
            $table->string('notes')->nullable();
            $table->decimal('shortage_value', 12, 2)->default(0);
            $table->decimal('excess_value', 12, 2)->default(0);
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });

        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained();
            // System quantity at the moment this item was counted (sales during the count do not create false variances)
            $table->decimal('expected_qty', 12, 3)->nullable();
            $table->decimal('counted_qty', 12, 3)->nullable();
            $table->decimal('variance', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->foreignUuid('counted_by')->nullable()->constrained('users');
            $table->dateTime('counted_at')->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
            $table->unique(['stock_take_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
    }
};
