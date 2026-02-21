<?php

namespace App\Services;

use App\Models\CryptoBalance;
use App\Models\CryptoTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CryptoBalanceService
{
    /**
     * Get or create user's crypto balance
     */
    public function getUserBalance(int $userId, string $currency = 'BTC'): CryptoBalance
    {
        return CryptoBalance::firstOrCreate(
            ['user_id' => $userId, 'currency' => $currency],
            ['balance' => 0, 'reserved_balance' => 0]
        );
    }

    /**
     * Deposit funds to user's balance
     */
    public function deposit(int $userId, float $amount, string $currency = 'BTC', ?string $referenceId = null, string $description = 'Депозит средств'): array
    {
        try {
            return DB::transaction(function () use ($userId, $amount, $currency, $referenceId, $description) {
                // Validate amount
                if ($amount <= 0) {
                    return [
                        'success' => false,
                        'error' => 'Сумма должна быть больше нуля',
                    ];
                }

                // Check if transaction already exists
                if ($referenceId && CryptoTransaction::where('reference_id', $referenceId)->exists()) {
                    return [
                        'success' => false,
                        'error' => 'Транзакция с таким reference_id уже существует',
                    ];
                }

                // Get or create balance
                $balance = $this->getUserBalance($userId, $currency);

                // Store previous values for transaction record
                $balanceBefore = $balance->balance;
                $reservedBefore = $balance->reserved_balance;

                // Update balance
                $balance->balance += $amount;
                $balance->save();

                // Create transaction record
                CryptoTransaction::create([
                    'crypto_balance_id' => $balance->id,
                    'user_id' => $userId,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balance->balance,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $balance->reserved_balance,
                    'reference_id' => $referenceId,
                    'description' => $description,
                    'status' => 'completed',
                ]);

                return [
                    'success' => true,
                    'message' => 'Средства успешно зачислены',
                    'balance' => $balance->balance,
                    'available_balance' => $balance->available_balance,
                ];
            }, 5); // 5 attempts for deadlock retry
        } catch (\Exception $e) {
            Log::error('Deposit error: '.$e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка при зачислении средств',
            ];
        }
    }

    /**
     * Withdraw funds from user's balance
     */
    public function withdraw(int $userId, float $amount, string $currency = 'BTC', ?string $referenceId = null, string $description = 'Вывод средств'): array
    {
        try {
            return DB::transaction(function () use ($userId, $amount, $currency, $referenceId, $description) {
                // Validate amount
                if ($amount <= 0) {
                    return [
                        'success' => false,
                        'error' => 'Сумма должна быть больше нуля',
                    ];
                }

                // Check if transaction already exists
                if ($referenceId && CryptoTransaction::where('reference_id', $referenceId)->exists()) {
                    return [
                        'success' => false,
                        'error' => 'Транзакция с таким reference_id уже существует',
                    ];
                }

                // Get user's balance
                $balance = CryptoBalance::where('user_id', $userId)
                    ->where('currency', $currency)
                    ->first();

                if (! $balance || $balance->available_balance < $amount) {
                    return [
                        'success' => false,
                        'error' => 'Недостаточно средств для вывода',
                    ];
                }

                // Store previous values for transaction records
                $balanceBefore = $balance->balance;
                $reservedBefore = $balance->reserved_balance;

                // Reserve funds first
                $balance->reserved_balance += $amount;
                $balance->save();

                // Create reservation transaction
                CryptoTransaction::create([
                    'crypto_balance_id' => $balance->id,
                    'user_id' => $userId,
                    'type' => 'reserve',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $balance->reserved_balance,
                    'reference_id' => $referenceId ? $referenceId.'_reserve' : null,
                    'description' => 'Резервирование средств для вывода',
                    'status' => 'completed',
                ]);

                // Deduct funds
                $balance->balance -= $amount;
                $balance->reserved_balance -= $amount;
                $balance->save();

                // Create withdrawal transaction
                CryptoTransaction::create([
                    'crypto_balance_id' => $balance->id,
                    'user_id' => $userId,
                    'type' => 'withdrawal',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balance->balance,
                    'reserved_before' => $balance->reserved_balance + $amount,
                    'reserved_after' => $balance->reserved_balance,
                    'reference_id' => $referenceId,
                    'description' => $description,
                    'status' => 'completed',
                ]);

                return [
                    'success' => true,
                    'message' => 'Средства успешно выведены',
                    'balance' => $balance->balance,
                    'available_balance' => $balance->available_balance,
                ];
            }, 5); // 5 attempts for deadlock retry
        } catch (\Exception $e) {
            // Try to release reserved funds in case of error
            try {
                $balance = CryptoBalance::where('user_id', $userId)
                    ->where('currency', $currency)
                    ->first();

                if ($balance && $balance->reserved_balance >= $amount) {
                    $balance->reserved_balance -= $amount;
                    $balance->save();
                }
            } catch (\Exception $rollbackException) {
                Log::error('Rollback error during withdrawal: '.$rollbackException->getMessage());
            }

            Log::error('Withdrawal error: '.$e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка при выводе средств',
            ];
        }
    }

    /**
     * Transfer funds between users
     */
    public function transfer(int $fromUserId, int $toUserId, float $amount, string $currency = 'BTC', string $description = 'Перевод средств'): array
    {
        // Prevent self-transfer
        if ($fromUserId == $toUserId) {
            return [
                'success' => false,
                'error' => 'Нельзя перевести средства самому себе',
            ];
        }

        try {
            return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $currency, $description) {
                // Validate amount
                if ($amount <= 0) {
                    return [
                        'success' => false,
                        'error' => 'Сумма должна быть больше нуля',
                    ];
                }

                // Get sender's balance
                $fromBalance = CryptoBalance::where('user_id', $fromUserId)
                    ->where('currency', $currency)
                    ->first();

                if (! $fromBalance || $fromBalance->available_balance < $amount) {
                    return [
                        'success' => false,
                        'error' => 'Недостаточно средств для перевода',
                    ];
                }

                // Get or create receiver's balance
                $toBalance = $this->getUserBalance($toUserId, $currency);

                // Store previous values for transaction records
                $fromBalanceBefore = $fromBalance->balance;
                $fromReservedBefore = $fromBalance->reserved_balance;
                $toBalanceBefore = $toBalance->balance;

                // Reserve funds from sender
                $fromBalance->reserved_balance += $amount;
                $fromBalance->save();

                // Create reservation transaction for sender
                CryptoTransaction::create([
                    'crypto_balance_id' => $fromBalance->id,
                    'user_id' => $fromUserId,
                    'type' => 'reserve',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $fromBalanceBefore,
                    'balance_after' => $fromBalanceBefore,
                    'reserved_before' => $fromReservedBefore,
                    'reserved_after' => $fromBalance->reserved_balance,
                    'description' => 'Резервирование средств для перевода',
                    'status' => 'completed',
                ]);

                // Transfer funds
                $fromBalance->balance -= $amount;
                $fromBalance->reserved_balance -= $amount;
                $fromBalance->save();

                $toBalance->balance += $amount;
                $toBalance->save();

                // Create transfer transaction for sender
                CryptoTransaction::create([
                    'crypto_balance_id' => $fromBalance->id,
                    'user_id' => $fromUserId,
                    'type' => 'transfer',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $fromBalanceBefore,
                    'balance_after' => $fromBalance->balance,
                    'reserved_before' => $fromBalance->reserved_balance + $amount,
                    'reserved_after' => $fromBalance->reserved_balance,
                    'description' => $description.' (отправитель)',
                    'status' => 'completed',
                ]);

                // Create transfer transaction for receiver
                CryptoTransaction::create([
                    'crypto_balance_id' => $toBalance->id,
                    'user_id' => $toUserId,
                    'type' => 'transfer',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $toBalanceBefore,
                    'balance_after' => $toBalance->balance,
                    'reserved_before' => $toBalance->reserved_balance,
                    'reserved_after' => $toBalance->reserved_balance,
                    'description' => $description.' (получатель)',
                    'status' => 'completed',
                ]);

                return [
                    'success' => true,
                    'message' => 'Средства успешно переведены',
                    'balance' => $fromBalance->balance,
                    'available_balance' => $fromBalance->available_balance,
                ];
            }, 5); // 5 attempts for deadlock retry
        } catch (\Exception $e) {
            // Try to release reserved funds in case of error
            try {
                $fromBalance = CryptoBalance::where('user_id', $fromUserId)
                    ->where('currency', $currency)
                    ->first();

                if ($fromBalance && $fromBalance->reserved_balance >= $amount) {
                    $fromBalance->reserved_balance -= $amount;
                    $fromBalance->save();
                }
            } catch (\Exception $rollbackException) {
                Log::error('Rollback error during transfer: '.$rollbackException->getMessage());
            }

            Log::error('Transfer error: '.$e->getMessage(), [
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка при переводе средств',
            ];
        }
    }

    /**
     * Charge fee from user's balance
     */
    public function chargeFee(int $userId, float $amount, string $currency = 'BTC', ?string $referenceId = null, string $description = 'Комиссия'): array
    {
        try {
            return DB::transaction(function () use ($userId, $amount, $currency, $referenceId, $description) {
                // Validate amount
                if ($amount <= 0) {
                    return [
                        'success' => false,
                        'error' => 'Сумма должна быть больше нуля',
                    ];
                }

                // Check if transaction already exists
                if ($referenceId && CryptoTransaction::where('reference_id', $referenceId)->exists()) {
                    return [
                        'success' => false,
                        'error' => 'Транзакция с таким reference_id уже существует',
                    ];
                }

                // Get user's balance
                $balance = CryptoBalance::where('user_id', $userId)
                    ->where('currency', $currency)
                    ->first();

                if (! $balance || $balance->available_balance < $amount) {
                    return [
                        'success' => false,
                        'error' => 'Недостаточно средств для оплаты комиссии',
                    ];
                }

                // Store previous values for transaction record
                $balanceBefore = $balance->balance;
                $reservedBefore = $balance->reserved_balance;

                // Reserve and deduct funds
                $balance->reserved_balance += $amount;
                $balance->balance -= $amount;
                $balance->reserved_balance -= $amount;
                $balance->save();

                // Create fee transaction
                CryptoTransaction::create([
                    'crypto_balance_id' => $balance->id,
                    'user_id' => $userId,
                    'type' => 'fee',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balance->balance,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $balance->reserved_balance,
                    'reference_id' => $referenceId,
                    'description' => $description,
                    'status' => 'completed',
                ]);

                return [
                    'success' => true,
                    'message' => 'Комиссия успешно списана',
                    'balance' => $balance->balance,
                    'available_balance' => $balance->available_balance,
                ];
            }, 5); // 5 attempts for deadlock retry
        } catch (\Exception $e) {
            Log::error('Fee charge error: '.$e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка при списании комиссии',
            ];
        }
    }

    /**
     * Refund funds to user's balance
     */
    public function refund(int $userId, float $amount, string $currency = 'BTC', ?string $referenceId = null, string $description = 'Возврат средств'): array
    {
        try {
            return DB::transaction(function () use ($userId, $amount, $currency, $referenceId, $description) {
                // Validate amount
                if ($amount <= 0) {
                    return [
                        'success' => false,
                        'error' => 'Сумма должна быть больше нуля',
                    ];
                }

                // Check if transaction already exists
                if ($referenceId && CryptoTransaction::where('reference_id', $referenceId)->exists()) {
                    return [
                        'success' => false,
                        'error' => 'Транзакция с таким reference_id уже существует',
                    ];
                }

                // Get or create balance
                $balance = $this->getUserBalance($userId, $currency);

                // Store previous values for transaction record
                $balanceBefore = $balance->balance;
                $reservedBefore = $balance->reserved_balance;

                // Add funds to balance
                $balance->balance += $amount;
                $balance->save();

                // Create refund transaction
                CryptoTransaction::create([
                    'crypto_balance_id' => $balance->id,
                    'user_id' => $userId,
                    'type' => 'refund',
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balance->balance,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $balance->reserved_balance,
                    'reference_id' => $referenceId,
                    'description' => $description,
                    'status' => 'completed',
                ]);

                return [
                    'success' => true,
                    'message' => 'Средства успешно возвращены',
                    'balance' => $balance->balance,
                    'available_balance' => $balance->available_balance,
                ];
            }, 5); // 5 attempts for deadlock retry
        } catch (\Exception $e) {
            Log::error('Refund error: '.$e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка при возврате средств',
            ];
        }
    }
}
