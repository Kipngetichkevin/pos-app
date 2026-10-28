<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_no', 30)->unique();
            $table->foreignUuid('sale_id')->constrained();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->string('reason');
            $table->enum('refund_method', ['cash', 'mpesa', 'card', 'credit', 'voucher']);
            $table->decimal('total_refunded', 12, 2);
            $table->dateTime('returned_at');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
