<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cloud side: each branch server authenticates with its own token (stored as a SHA-256 hash)
        Schema::table('branches', function (Blueprint $table) {
            $table->string('sync_token_hash', 64)->nullable()->unique()->after('is_active');
            $table->timestamp('last_sync_at')->nullable()->after('sync_token_hash');
        });

        // Branch side: pull cursors per table and the result of the last sync run
        Schema::create('sync_states', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['sync_token_hash']);
            $table->dropColumn(['sync_token_hash', 'last_sync_at']);
        });
    }
};
