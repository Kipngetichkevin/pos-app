<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('parent_id')->nullable()->after('id')->constrained('products')->nullOnDelete();
            $table->decimal('pack_size', 12, 3)->nullable()->after('parent_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('type', [
                'sale', 'return', 'purchase', 'adjustment', 'transfer_in', 'transfer_out',
                'damage', 'opening', 'repack_in', 'repack_out',
            ])->change();
        });

        Schema::create('repacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('product_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->decimal('bulk_used', 12, 3);
            $table->decimal('packed_quantity', 12, 3);
            $table->decimal('cost_total', 12, 2);
            $table->json('packs')->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repacks');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('pack_size');
        });
    }
};
