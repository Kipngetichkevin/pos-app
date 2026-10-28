<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_no', 30)->unique(); // NRB01-T02-000154
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('till_id')->constrained();
            $table->foreignUuid('shift_id')->constrained();
            $table->foreignUuid('user_id')->constrained(); // cashier
            $table->foreignUuid('customer_id')->nullable()->constrained();
            $table->enum('status', ['completed', 'voided', 'refunded', 'partially_refunded'])->default('completed');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('change_given', 12, 2)->default(0);
            $table->enum('etims_status', ['pending', 'submitted', 'failed', 'not_required'])->default('pending');
            $table->foreignUuid('voided_by')->nullable()->constrained('users');
            $table->string('void_reason')->nullable();
            $table->dateTime('sold_at')->index(); // real time of sale, even if synced later
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
            $table->index(['branch_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
