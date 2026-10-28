<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('shift_id')->nullable()->constrained()->nullOnDelete(); // cash taken from this till
            $table->foreignUuid('user_id')->constrained();
            $table->string('category', 30);
            $table->string('description', 200);
            $table->string('paid_to', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'mpesa', 'bank']);
            $table->string('reference', 50)->nullable(); // M-Pesa code / cheque no.
            $table->dateTime('spent_at')->index();
            $table->timestamps();
            $table->softDeletes(); // removal must sync to the cloud, so rows are never hard-deleted
            $table->timestamp('synced_at')->nullable()->index();
            $table->index(['branch_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
