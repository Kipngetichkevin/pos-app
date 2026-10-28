<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('product_id')->constrained();
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity_received', 12, 3);
            $table->decimal('quantity_remaining', 12, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
            $table->index(['branch_id', 'product_id', 'expiry_date']); // FEFO lookups
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
