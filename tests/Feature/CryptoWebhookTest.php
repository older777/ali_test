<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CryptoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function webhook_can_process_confirmed_deposit()
    {
        $payload = [
            'event' => 'deposit_confirmed',
            'user_id' => $this->user->id,
            'amount' => 1.5,
            'currency' => 'BTC',
            'transaction_id' => 'blockchain_tx_123456',
            'description' => 'Confirmed deposit from blockchain',
        ];

        $response = $this->postJson('/webhook/crypto', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Deposit confirmed and processed',
        ]);

        $this->assertDatabaseHas('crypto_balances', [
            'user_id' => $this->user->id,
            'currency' => 'BTC',
            'balance' => 1.5,
        ]);

        $this->assertDatabaseHas('crypto_transactions', [
            'user_id' => $this->user->id,
            'type' => 'deposit',
            'amount' => 1.5,
            'currency' => 'BTC',
            'reference_id' => 'blockchain_tx_123456',
        ]);
    }

    /** @test */
    public function webhook_rejects_invalid_data()
    {
        $payload = [
            'event' => 'deposit_confirmed',
            'user_id' => 999999, // Non-existent user
            'amount' => 1.5,
            'currency' => 'BTC',
            'transaction_id' => 'blockchain_tx_123456',
        ];

        $response = $this->postJson('/webhook/crypto', $payload);

        $response->assertStatus(400);
    }

    /** @test */
    public function webhook_can_handle_withdrawal_confirmation()
    {
        $payload = [
            'event' => 'withdrawal_confirmed',
            'user_id' => $this->user->id,
            'amount' => 1.0,
            'currency' => 'BTC',
            'transaction_id' => 'blockchain_withdrawal_123456',
        ];

        $response = $this->postJson('/webhook/crypto', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Withdrawal confirmed',
        ]);
    }

    /** @test */
    public function webhook_can_handle_deposit_failure()
    {
        $payload = [
            'event' => 'deposit_failed',
            'user_id' => $this->user->id,
            'amount' => 1.5,
            'currency' => 'BTC',
            'transaction_id' => 'failed_tx_123456',
        ];

        $response = $this->postJson('/webhook/crypto', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Deposit failed recorded',
        ]);
    }

    /** @test */
    public function webhook_can_handle_withdrawal_failure()
    {
        $payload = [
            'event' => 'withdrawal_failed',
            'user_id' => $this->user->id,
            'amount' => 1.0,
            'currency' => 'BTC',
            'transaction_id' => 'failed_withdrawal_123456',
        ];

        $response = $this->postJson('/webhook/crypto', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Withdrawal failed recorded',
        ]);
    }
}
