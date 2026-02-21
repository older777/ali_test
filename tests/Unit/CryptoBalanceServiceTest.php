<?php

namespace Tests\Unit;

use App\Models\CryptoBalance;
use App\Models\User;
use App\Services\CryptoBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CryptoBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CryptoBalanceService $cryptoBalanceService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cryptoBalanceService = new CryptoBalanceService;
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_get_or_create_user_balance()
    {
        $balance = $this->cryptoBalanceService->getUserBalance($this->user->id, 'BTC');

        $this->assertInstanceOf(CryptoBalance::class, $balance);
        $this->assertEquals($this->user->id, $balance->user_id);
        $this->assertEquals('BTC', $balance->currency);
        $this->assertEquals(0, $balance->balance);
        $this->assertEquals(0, $balance->reserved_balance);
    }

    /** @test */
    public function it_can_deposit_funds()
    {
        $result = $this->cryptoBalanceService->deposit(
            $this->user->id,
            1.5,
            'BTC',
            'test_deposit_1',
            'Test deposit'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Средства успешно зачислены', $result['message']);

        $balance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.5, $balance->balance);
        $this->assertEquals(0, $balance->reserved_balance);
    }

    /** @test */
    public function it_can_withdraw_funds()
    {
        // First deposit funds
        $this->cryptoBalanceService->deposit($this->user->id, 2.0, 'BTC', 'test_deposit_2', 'Test deposit');

        // Then withdraw
        $result = $this->cryptoBalanceService->withdraw(
            $this->user->id,
            1.0,
            'BTC',
            'test_withdraw_1',
            'Test withdrawal'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Средства успешно выведены', $result['message']);

        $balance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.0, $balance->balance);
        $this->assertEquals(0, $balance->reserved_balance);
    }

    /** @test */
    public function it_cannot_withdraw_more_than_available_balance()
    {
        // First deposit funds
        $this->cryptoBalanceService->deposit($this->user->id, 1.0, 'BTC', 'test_deposit_3', 'Test deposit');

        // Try to withdraw more than available
        $result = $this->cryptoBalanceService->withdraw(
            $this->user->id,
            2.0,
            'BTC',
            'test_withdraw_2',
            'Test withdrawal'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Недостаточно средств для вывода', $result['error']);

        $balance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.0, $balance->balance); // Balance should remain unchanged
    }

    /** @test */
    public function it_can_transfer_funds_between_users()
    {
        $recipient = User::factory()->create();

        // First deposit funds to sender
        $this->cryptoBalanceService->deposit($this->user->id, 2.0, 'BTC', 'test_deposit_4', 'Test deposit');

        // Transfer funds
        $result = $this->cryptoBalanceService->transfer(
            $this->user->id,
            $recipient->id,
            1.0,
            'BTC',
            'Test transfer'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Средства успешно переведены', $result['message']);

        // Check sender's balance
        $senderBalance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.0, $senderBalance->balance);

        // Check recipient's balance
        $recipientBalance = CryptoBalance::where('user_id', $recipient->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.0, $recipientBalance->balance);
    }

    /** @test */
    public function it_cannot_transfer_to_the_same_user()
    {
        $result = $this->cryptoBalanceService->transfer(
            $this->user->id,
            $this->user->id,
            1.0,
            'BTC',
            'Test transfer'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Нельзя перевести средства самому себе', $result['error']);
    }

    /** @test */
    public function it_can_charge_fee()
    {
        // First deposit funds
        $this->cryptoBalanceService->deposit($this->user->id, 2.0, 'BTC', 'test_deposit_5', 'Test deposit');

        // Charge fee
        $result = $this->cryptoBalanceService->chargeFee(
            $this->user->id,
            0.1,
            'BTC',
            'test_fee_1',
            'Test fee'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Комиссия успешно списана', $result['message']);

        $balance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.9, $balance->balance);
        $this->assertEquals(0, $balance->reserved_balance);
    }

    /** @test */
    public function it_can_refund_funds()
    {
        // Refund funds
        $result = $this->cryptoBalanceService->refund(
            $this->user->id,
            1.0,
            'BTC',
            'test_refund_1',
            'Test refund'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Средства успешно возвращены', $result['message']);

        $balance = CryptoBalance::where('user_id', $this->user->id)->where('currency', 'BTC')->first();
        $this->assertEquals(1.0, $balance->balance);
        $this->assertEquals(0, $balance->reserved_balance);
    }
}
