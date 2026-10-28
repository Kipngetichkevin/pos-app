<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_item_id')->constrained();
            $table->decimal('quantity', 12, 3);
            $table->decimal('refund_amount', 12, 2);
            $table->boolean('restock')->default(true); // false if damaged
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
