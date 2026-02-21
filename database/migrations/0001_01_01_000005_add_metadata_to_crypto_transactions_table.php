<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crypto_transactions', function (Blueprint $table) {
            // Make metadata column non-nullable since it's already defined in the table
            $table->json('metadata')->nullable()->change();

            // Add additional columns for better tracking
            $table->string('external_id')->nullable()->after('reference_id');
            $table->string('blockchain_tx_id')->nullable()->after('external_id');
            $table->integer('confirmations')->default(0)->after('blockchain_tx_id');
            $table->timestamp('processed_at')->nullable()->after('confirmations');

            // Add indexes
            $table->index('external_id');
            $table->index('blockchain_tx_id');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crypto_transactions', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'blockchain_tx_id', 'confirmations', 'processed_at']);
        });
    }
};
