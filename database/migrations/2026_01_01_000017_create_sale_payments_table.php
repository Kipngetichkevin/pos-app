<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'mpesa', 'card', 'credit', 'voucher']);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 50)->nullable(); // M-Pesa code / card approval code
            $table->foreignUuid('mpesa_transaction_id')->nullable()->constrained();
            $table->enum('status', ['confirmed', 'pending', 'failed'])->default('confirmed');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
