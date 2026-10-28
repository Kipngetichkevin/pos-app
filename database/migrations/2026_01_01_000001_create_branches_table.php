<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique(); // e.g. NRB01, used in receipt numbers
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('kra_pin', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
