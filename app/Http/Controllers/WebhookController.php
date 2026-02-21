<?php

namespace App\Http\Controllers;

use App\Services\CryptoBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    protected CryptoBalanceService $cryptoBalanceService;

    public function __construct(CryptoBalanceService $cryptoBalanceService)
    {
        $this->cryptoBalanceService = $cryptoBalanceService;
    }

    /**
     * Handle blockchain webhook notifications
     */
    public function handleCryptoWebhook(Request $request): JsonResponse
    {
        try {
            // Log incoming webhook
            Log::info('Crypto webhook received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Validate webhook signature (implementation depends on blockchain provider)
            if (! $this->validateWebhookSignature($request)) {
                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }

            $data = $request->all();
            $eventType = $data['event'] ?? $data['type'] ?? null;

            switch ($eventType) {
                case 'deposit_confirmed':
                    return $this->handleDepositConfirmed($data);
                case 'withdrawal_confirmed':
                    return $this->handleWithdrawalConfirmed($data);
                case 'deposit_failed':
                    return $this->handleDepositFailed($data);
                case 'withdrawal_failed':
                    return $this->handleWithdrawalFailed($data);
                default:
                    Log::warning('Unknown webhook event type', ['event_type' => $eventType]);

                    return response()->json(['error' => 'Unknown event type'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing error: '.$e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle confirmed deposit
     */
    protected function handleDepositConfirmed(array $data): JsonResponse
    {
        $validator = Validator::make($data, [
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.00000001',
            'currency' => 'required|string|in:BTC,ETH,USDT',
            'transaction_id' => 'required|string',
            'blockchain_tx_id' => 'required|string',
            'confirmations' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid deposit confirmed webhook data', $data);

            return response()->json(['error' => 'Invalid data'], 400);
        }

        $userId = $data['user_id'];
        $amount = $data['amount'];
        $currency = $data['currency'];
        $transactionId = $data['transaction_id'];
        $blockchainTxId = $data['blockchain_tx_id'];
        $confirmations = $data['confirmations'];
        $description = $data['description'] ?? 'Подтвержденный депозит';

        // Add blockchain metadata
        $metadata = [
            'blockchain_tx_id' => $blockchainTxId,
            'confirmations' => $confirmations,
            'processed_at' => now()->toISOString(),
        ];

        // Check if this is an external deposit that needs to be recorded
        // or if it's confirming an existing pending deposit
        $result = $this->cryptoBalanceService->deposit(
            $userId,
            $amount,
            $currency,
            $transactionId,
            $description,
            $metadata
        );

        if ($result['success']) {
            // Update the transaction with blockchain data
            if (isset($result['transaction_id'])) {
                $this->cryptoBalanceService->updateTransactionWithBlockchainData(
                    $result['transaction_id'],
                    $blockchainTxId,
                    $confirmations,
                    $metadata
                );
            }

            Log::info('Deposit confirmed and processed', [
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'blockchain_tx_id' => $blockchainTxId,
            ]);

            return response()->json(['message' => 'Deposit confirmed and processed']);
        } else {
            Log::error('Failed to process confirmed deposit', [
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'error' => $result['error'],
            ]);

            return response()->json(['error' => $result['error']], 400);
        }
    }

    /**
     * Handle confirmed withdrawal
     */
    protected function handleWithdrawalConfirmed(array $data): JsonResponse
    {
        $validator = Validator::make($data, [
            'transaction_id' => 'required|string|exists:crypto_transactions,reference_id',
            'blockchain_tx_id' => 'required|string',
            'confirmations' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid withdrawal confirmed webhook data', $data);

            return response()->json(['error' => 'Invalid data'], 400);
        }

        $transactionId = $data['transaction_id'];
        $blockchainTxId = $data['blockchain_tx_id'];
        $confirmations = $data['confirmations'];

        // Add blockchain metadata
        $metadata = [
            'blockchain_tx_id' => $blockchainTxId,
            'confirmations' => $confirmations,
            'processed_at' => now()->toISOString(),
        ];

        // Update the transaction with blockchain data
        $result = $this->cryptoBalanceService->updateTransactionWithBlockchainData(
            $transactionId,
            $blockchainTxId,
            $confirmations,
            $metadata
        );

        if ($result['success']) {
            Log::info('Withdrawal confirmed', [
                'transaction_id' => $transactionId,
                'blockchain_tx_id' => $blockchainTxId,
                'confirmations' => $confirmations,
            ]);

            return response()->json(['message' => 'Withdrawal confirmed']);
        } else {
            Log::error('Failed to process confirmed withdrawal', [
                'transaction_id' => $transactionId,
                'error' => $result['error'],
            ]);

            return response()->json(['error' => $result['error']], 400);
        }
    }

    /**
     * Handle failed deposit
     */
    protected function handleDepositFailed(array $data): JsonResponse
    {
        $validator = Validator::make($data, [
            'transaction_id' => 'required|string',
            'reason' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid deposit failed webhook data', $data);

            return response()->json(['error' => 'Invalid data'], 400);
        }

        $transactionId = $data['transaction_id'];
        $reason = $data['reason'] ?? 'Unknown reason';

        // Add failure metadata
        $metadata = [
            'failed_at' => now()->toISOString(),
            'failure_reason' => $reason,
        ];

        // In a real implementation, you would handle the failed deposit
        // For now, we'll just log the event

        Log::info('Deposit failed, funds should be handled accordingly', [
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);

        return response()->json(['message' => 'Deposit failed recorded']);
    }

    /**
     * Handle failed withdrawal
     */
    protected function handleWithdrawalFailed(array $data): JsonResponse
    {
        $validator = Validator::make($data, [
            'transaction_id' => 'required|string|exists:crypto_transactions,reference_id',
            'reason' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid withdrawal failed webhook data', $data);

            return response()->json(['error' => 'Invalid data'], 400);
        }

        $transactionId = $data['transaction_id'];
        $reason = $data['reason'] ?? 'Unknown reason';

        // Add failure metadata
        $metadata = [
            'failed_at' => now()->toISOString(),
            'failure_reason' => $reason,
        ];

        // In a real implementation, you would release the reserved funds
        // For now, we'll just log the event

        Log::info('Withdrawal failed, funds should be released', [
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);

        return response()->json(['message' => 'Withdrawal failed recorded']);
    }

    /**
     * Validate webhook signature
     * This is a placeholder implementation - you should implement proper signature validation
     * based on your blockchain provider's documentation
     */
    protected function validateWebhookSignature(Request $request): bool
    {
        // Example implementation for signature validation:
        /*
        $signature = $request->header('X-Signature');
        $payload = $request->getContent();
        $secret = config('services.blockchain.webhook_secret');

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
        */

        // For now, return true but in production you should implement proper validation
        return true;
    }
}
