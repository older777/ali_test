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
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crypto_balance_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20); // deposit, withdrawal, transfer, reserve, release, fee, refund
            $table->decimal('amount', 16, 8);
            $table->string('currency', 10)->default('BTC');
            $table->decimal('balance_before', 16, 8)->default(0);
            $table->decimal('balance_after', 16, 8)->default(0);
            $table->decimal('reserved_before', 16, 8)->default(0);
            $table->decimal('reserved_after', 16, 8)->default(0);
            $table->string('reference_id')->nullable(); // External transaction ID
            $table->text('description')->nullable();
            $table->string('status', 20)->default('completed'); // pending, completed, failed, cancelled
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            // Indexes
            $table->index('crypto_balance_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('currency');
            $table->index('status');
            $table->index('reference_id');
            $table->index('created_at');

            // Foreign key constraints
            $table->foreign('crypto_balance_id')->references('id')->on('crypto_balances')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_transactions');
    }
};
