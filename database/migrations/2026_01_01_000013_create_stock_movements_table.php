<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('product_id')->constrained();
            $table->foreignUuid('batch_id')->nullable()->constrained('stock_batches');
            $table->enum('type', [
                'sale', 'return', 'purchase', 'adjustment',
                'transfer_in', 'transfer_out', 'damage', 'opening',
            ]);
            $table->decimal('quantity', 12, 3);      // positive = in, negative = out
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 3);
            $table->nullableUuidMorphs('reference'); // reference_type + reference_id (sale, goods receipt...)
            $table->foreignUuid('user_id')->constrained();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
            $table->index(['branch_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
