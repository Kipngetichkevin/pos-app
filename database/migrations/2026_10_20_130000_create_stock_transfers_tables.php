<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfer_no', 30)->unique(); // TR-MAIN-261020-01
            $table->foreignUuid('from_branch_id')->constrained('branches');
            $table->foreignUuid('to_branch_id')->constrained('branches');
            $table->enum('status', ['sent', 'received', 'cancelled'])->default('sent');
            $table->foreignUuid('sent_by')->constrained('users');
            $table->foreignUuid('received_by')->nullable()->constrained('users');
            $table->string('notes')->nullable();
            $table->string('receive_notes')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->dateTime('sent_at');
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained();
            $table->string('product_name', 150);
            $table->decimal('quantity_sent', 12, 3);      // base units (e.g. pieces, kg)
            $table->decimal('quantity_received', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 2);           // sending branch's average cost
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
