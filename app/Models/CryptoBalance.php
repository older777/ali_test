<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoBalance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        'reserved_balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:8',
        'reserved_balance' => 'decimal:8',
    ];

    /**
     * Get the user that owns the balance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions for the balance.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class);
    }

    /**
     * Get available balance (total balance minus reserved)
     */
    public function getAvailableBalanceAttribute(): float
    {
        return $this->balance - $this->reserved_balance;
    }

    /**
     * Scope a query to only include balances of a given currency.
     */
    public function scopeOfCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }
}
