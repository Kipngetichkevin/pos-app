<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50)->index();          // e.g. price.changed, sale.voided
            $table->string('subject_type', 100)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('description', 255);
            $table->json('changes')->nullable();            // {"selling_price": [100, 120]}
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
