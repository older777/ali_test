<?php

namespace App\Http\Controllers;

use App\Models\CryptoTransaction;
use App\Services\CryptoBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CryptoBalanceController extends Controller
{
    protected CryptoBalanceService $cryptoBalanceService;

    public function __construct(CryptoBalanceService $cryptoBalanceService)
    {
        $this->cryptoBalanceService = $cryptoBalanceService;
    }

    /**
     * Get user's crypto balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'string|in:BTC,ETH,USDT',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $currency = $request->get('currency', 'BTC');
        $userId = Auth::id();

        $balance = $this->cryptoBalanceService->getUserBalance($userId, $currency);

        return response()->json([
            'currency' => $balance->currency,
            'total_balance' => $balance->balance,
            'reserved_balance' => $balance->reserved_balance,
            'available_balance' => $balance->available_balance,
        ]);
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'string|in:BTC,ETH,USDT',
            'type' => 'string|in:deposit,withdrawal,transfer,reserve,release,fee,refund',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = Auth::id();
        $currency = $request->get('currency', 'BTC');
        $type = $request->get('type');
        $limit = $request->get('limit', 50);

        $query = CryptoTransaction::where('user_id', $userId)
            ->where('currency', $currency);

        if ($type) {
            $query->where('type', $type);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    /**
     * Deposit funds
     */
    public function deposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.00000001',
            'currency' => 'string|in:BTC,ETH,USDT',
            'reference_id' => 'string|max:255',
            'description' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = Auth::id();
        $amount = $request->get('amount');
        $currency = $request->get('currency', 'BTC');
        $referenceId = $request->get('reference_id');
        $description = $request->get('description', 'Депозит средств');

        $result = $this->cryptoBalanceService->deposit($userId, $amount, $currency, $referenceId, $description);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'balance' => $result['balance'],
                'available_balance' => $result['available_balance'],
            ]);
        } else {
            return response()->json(['error' => $result['error']], 400);
        }
    }

    /**
     * Withdraw funds
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.00000001',
            'currency' => 'string|in:BTC,ETH,USDT',
            'reference_id' => 'string|max:255',
            'description' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = Auth::id();
        $amount = $request->get('amount');
        $currency = $request->get('currency', 'BTC');
        $referenceId = $request->get('reference_id');
        $description = $request->get('description', 'Вывод средств');

        $result = $this->cryptoBalanceService->withdraw($userId, $amount, $currency, $referenceId, $description);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'balance' => $result['balance'],
                'available_balance' => $result['available_balance'],
            ]);
        } else {
            return response()->json(['error' => $result['error']], 400);
        }
    }

    /**
     * Transfer funds to another user
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to_user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.00000001',
            'currency' => 'string|in:BTC,ETH,USDT',
            'description' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $fromUserId = Auth::id();
        $toUserId = $request->get('to_user_id');
        $amount = $request->get('amount');
        $currency = $request->get('currency', 'BTC');
        $description = $request->get('description', 'Перевод средств');

        $result = $this->cryptoBalanceService->transfer($fromUserId, $toUserId, $amount, $currency, $description);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'balance' => $result['balance'],
                'available_balance' => $result['available_balance'],
            ]);
        } else {
            return response()->json(['error' => $result['error']], 400);
        }
    }

    /**
     * Get transaction details
     */
    public function getTransactionDetails(Request $request, $transactionId): JsonResponse
    {
        $transaction = CryptoTransaction::where('id', $transactionId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $transaction) {
            return response()->json([
                'error' => 'Транзакция не найдена',
            ], 404);
        }

        return response()->json([
            'transaction' => $transaction,
        ]);
    }
}
