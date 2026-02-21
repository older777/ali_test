<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CryptoOperationLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $operationType = 'general'): Response
    {
        $user = Auth::user();
        if (! $user) {
            return $next($request);
        }

        $userId = $user->id;
        $timeWindow = 3600; // 1 hour
        $limit = $this->getLimitForOperation($operationType);

        $key = "crypto_operation_limit:{$userId}:{$operationType}";
        $attempts = Cache::get($key, 0);

        if ($attempts >= $limit) {
            return response()->json([
                'error' => 'Превышен лимит операций. Пожалуйста, попробуйте позже.',
            ], 429);
        }

        // Increment the counter
        if ($attempts === 0) {
            Cache::put($key, 1, $timeWindow);
        } else {
            Cache::increment($key);
        }

        $response = $next($request);

        // Add rate limit headers
        $remaining = $limit - ($attempts + 1);
        $response->headers->add([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($timeWindow)->timestamp,
        ]);

        return $response;
    }

    /**
     * Get limit for operation type
     */
    protected function getLimitForOperation(string $operationType): int
    {
        $limits = [
            'deposit' => 10,
            'withdraw' => 5,
            'transfer' => 20,
            'general' => 60,
        ];

        return $limits[$operationType] ?? $limits['general'];
    }
}
