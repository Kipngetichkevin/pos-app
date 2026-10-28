<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50);        // Piece, Kilogram, Litre, Crate
            $table->string('short_name', 10);  // pc, kg, ltr, crt
            $table->boolean('allows_decimal')->default(false); // true for kg, ltr
            $table->timestamps();
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
