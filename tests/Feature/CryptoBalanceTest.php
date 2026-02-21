<?php

namespace Tests\Feature;

use App\Models\CryptoBalance;
use App\Models\CryptoTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CryptoBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_get_their_balance()
    {
        $this->actingAs($this->user);

        $response = $this->get('/balance');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'currency',
            'total_balance',
            'reserved_balance',
            'available_balance',
        ]);
    }

    /** @test */
    public function user_can_deposit_funds()
    {
        $this->actingAs($this->user);

        $response = $this->post('/balance/deposit', [
            'amount' => 1.5,
            'currency' => 'BTC',
            'reference_id' => 'test_deposit_1',
            'description' => 'Test deposit',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Средства успешно зачислены',
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
        ]);
    }

    /** @test */
    public function user_can_withdraw_funds()
    {
        // First deposit funds
        $this->actingAs($this->user);

        $this->post('/balance/deposit', [
            'amount' => 2.0,
            'currency' => 'BTC',
            'reference_id' => 'test_deposit_2',
            'description' => 'Test deposit',
        ]);

        // Then withdraw
        $response = $this->post('/balance/withdraw', [
            'amount' => 1.0,
            'currency' => 'BTC',
            'reference_id' => 'test_withdraw_1',
            'description' => 'Test withdrawal',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Средства успешно выведены',
        ]);

        $this->assertDatabaseHas('crypto_balances', [
            'user_id' => $this->user->id,
            'currency' => 'BTC',
            'balance' => 1.0,
        ]);

        $balance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.0, $balance->available_balance);
    }

    /** @test */
    public function user_cannot_withdraw_more_than_available_balance()
    {
        // First deposit funds
        $this->actingAs($this->user);

        $this->post('/balance/deposit', [
            'amount' => 1.0,
            'currency' => 'BTC',
            'reference_id' => 'test_deposit_3',
            'description' => 'Test deposit',
        ]);

        // Try to withdraw more than available
        $response = $this->post('/balance/withdraw', [
            'amount' => 2.0,
            'currency' => 'BTC',
            'reference_id' => 'test_withdraw_2',
            'description' => 'Test withdrawal',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Недостаточно средств для вывода',
        ]);
    }

    /** @test */
    public function user_can_transfer_funds_to_another_user()
    {
        $recipient = User::factory()->create();

        // First deposit funds
        $this->actingAs($this->user);

        $this->post('/balance/deposit', [
            'amount' => 2.0,
            'currency' => 'BTC',
            'reference_id' => 'test_deposit_4',
            'description' => 'Test deposit',
        ]);

        // Transfer funds
        $response = $this->post('/balance/transfer', [
            'to_user_id' => $recipient->id,
            'amount' => 1.0,
            'currency' => 'BTC',
            'description' => 'Test transfer',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Средства успешно переведены',
        ]);

        // Check sender's balance
        $this->assertDatabaseHas('crypto_balances', [
            'user_id' => $this->user->id,
            'currency' => 'BTC',
            'balance' => 1.0,
        ]);

        // Check recipient's balance
        $this->assertDatabaseHas('crypto_balances', [
            'user_id' => $recipient->id,
            'currency' => 'BTC',
            'balance' => 1.0,
        ]);
    }

    /** @test */
    public function user_cannot_transfer_to_themselves()
    {
        $this->actingAs($this->user);

        $response = $this->post('/balance/transfer', [
            'to_user_id' => $this->user->id,
            'amount' => 1.0,
            'currency' => 'BTC',
            'description' => 'Test transfer',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Нельзя перевести средства самому себе',
        ]);
    }

    /** @test */
    public function user_can_get_transaction_history()
    {
        // First deposit funds
        $this->actingAs($this->user);

        $this->post('/balance/deposit', [
            'amount' => 1.0,
            'currency' => 'BTC',
            'reference_id' => 'test_deposit_5',
            'description' => 'Test deposit',
        ]);

        // Get transaction history
        $response = $this->get('/balance/transactions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transactions' => [
                '*' => [
                    'id',
                    'type',
                    'type_label',
                    'amount',
                    'currency',
                    'description',
                    'created_at',
                ],
            ],
        ]);
    }

    /** @test */
    public function user_can_get_transaction_details()
    {
        // First deposit funds
        $this->actingAs($this->user);

        $this->post('/balance/deposit', [
            'amount' => 1.0,
            'currency' => 'BTC',
            'reference_id' => 'test_deposit_6',
            'description' => 'Test deposit',
        ]);

        $transaction = CryptoTransaction::where('user_id', $this->user->id)->first();

        // Get transaction details
        $response = $this->get("/balance/transaction/{$transaction->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transaction' => [
                'id',
                'type',
                'type_label',
                'amount',
                'currency',
                'balance_before',
                'balance_after',
                'reserved_before',
                'reserved_after',
                'description',
                'status',
                'created_at',
            ],
        ]);
    }
}
