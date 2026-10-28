<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('product_id')->constrained();
            $table->decimal('quantity', 12, 3)->default(0); // only changed via stock_movements
            $table->decimal('average_cost', 12, 2)->default(0);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
