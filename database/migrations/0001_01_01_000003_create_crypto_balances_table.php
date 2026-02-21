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
        Schema::create('crypto_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('currency', 10)->default('BTC');
            $table->decimal('balance', 16, 8)->default(0);
            $table->decimal('reserved_balance', 16, 8)->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'currency']);
            $table->index('currency');

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_balances');
    }
};
