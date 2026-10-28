<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('till_id')->nullable()->constrained();
            $table->enum('type', ['stk_push', 'c2b']);
            $table->string('merchant_request_id', 100)->nullable();
            $table->string('checkout_request_id', 100)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('mpesa_receipt', 30)->nullable()->unique(); // e.g. QGH12ABC34
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled'])->default('pending');
            $table->string('result_code', 10)->nullable();
            $table->string('result_desc')->nullable();
            $table->json('raw_callback')->nullable();
            $table->dateTime('transacted_at')->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
