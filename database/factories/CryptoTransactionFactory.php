<?php

namespace Database\Factories;

use App\Models\CryptoBalance;
use App\Models\CryptoTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CryptoTransaction>
 */
class CryptoTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CryptoTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cryptoBalance = CryptoBalance::factory()->create();

        return [
            'crypto_balance_id' => $cryptoBalance->id,
            'user_id' => $cryptoBalance->user_id,
            'type' => $this->faker->randomElement(['deposit', 'withdrawal', 'transfer', 'reserve', 'release', 'fee', 'refund']),
            'amount' => $this->faker->randomFloat(8, 0.00000001, 10),
            'currency' => $cryptoBalance->currency,
            'balance_before' => $this->faker->randomFloat(8, 0, 100),
            'balance_after' => $this->faker->randomFloat(8, 0, 100),
            'reserved_before' => $this->faker->randomFloat(8, 0, 10),
            'reserved_after' => $this->faker->randomFloat(8, 0, 10),
            'reference_id' => $this->faker->uuid(),
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'cancelled']),
        ];
    }

    /**
     * Indicate that the transaction is a deposit.
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deposit',
        ]);
    }

    /**
     * Indicate that the transaction is a withdrawal.
     */
    public function withdrawal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'withdrawal',
        ]);
    }

    /**
     * Indicate that the transaction is a transfer.
     */
    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
        ]);
    }

    /**
     * Indicate that the transaction is a fee.
     */
    public function fee(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fee',
        ]);
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
