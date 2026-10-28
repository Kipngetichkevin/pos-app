<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->change();
            $table->string('first_name', 100)->nullable()->after('phone');
            $table->string('bill_ref', 50)->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'bill_ref']);
        });
    }
};
