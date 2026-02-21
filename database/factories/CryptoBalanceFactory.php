<?php

namespace Database\Factories;

use App\Models\CryptoBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CryptoBalance>
 */
class CryptoBalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CryptoBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency' => $this->faker->randomElement(['BTC', 'ETH', 'USDT']),
            'balance' => $this->faker->randomFloat(8, 0, 100),
            'reserved_balance' => $this->faker->randomFloat(8, 0, 10),
        ];
    }

    /**
     * Indicate that the balance has a specific currency.
     */
    public function btc(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'BTC',
        ]);
    }

    /**
     * Indicate that the balance has a specific currency.
     */
    public function eth(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'ETH',
        ]);
    }

    /**
     * Indicate that the balance has a specific currency.
     */
    public function usdt(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USDT',
        ]);
    }

    /**
     * Indicate that the balance has zero balance.
     */
    public function zero(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
            'reserved_balance' => 0,
        ]);
    }
}
