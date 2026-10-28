<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per invoice or credit note sent (or to be sent) to KRA
        Schema::create('etims_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained();
            $table->foreignUuid('sale_id')->constrained();
            $table->foreignUuid('sale_return_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['invoice', 'credit_note']);
            $table->unsignedBigInteger('invoice_no')->nullable();      // our gap-free number, given when first sent
            $table->unsignedBigInteger('original_invoice_no')->nullable(); // credit notes: the invoice they reverse
            $table->enum('status', ['queued', 'sent', 'failed', 'not_required'])->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            // What KRA returns, printed on the receipt
            $table->string('kra_receipt_no', 50)->nullable();
            $table->string('kra_total_receipt_no', 50)->nullable();
            $table->string('internal_data', 100)->nullable();
            $table->string('receipt_signature', 100)->nullable();
            $table->string('sdc_id', 50)->nullable();
            $table->string('mrc_no', 50)->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->dateTime('next_attempt_at')->nullable()->index();
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('etims_invoice_sequence')->default(0)->after('kra_pin');
        });

        // KRA item codes: every product must be registered with eTIMS before it appears on an invoice
        Schema::table('products', function (Blueprint $table) {
            $table->string('etims_item_code', 20)->nullable()->unique()->after('sku');
            $table->string('etims_class_code', 10)->nullable()->after('etims_item_code'); // UNSPSC-based classification
            $table->timestamp('etims_registered_at')->nullable()->after('etims_class_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['etims_item_code']);
            $table->dropColumn(['etims_item_code', 'etims_class_code', 'etims_registered_at']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('etims_invoice_sequence');
        });

        Schema::dropIfExists('etims_invoices');
    }
};
