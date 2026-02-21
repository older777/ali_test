<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'crypto_balance_id',
        'user_id',
        'type',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'reserved_before',
        'reserved_after',
        'reference_id',
        'external_id',
        'blockchain_tx_id',
        'confirmations',
        'description',
        'status',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'reserved_before' => 'decimal:8',
        'reserved_after' => 'decimal:8',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'reference_id',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'type_label',
    ];

    /**
     * Get the balance that owns the transaction.
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(CryptoBalance::class, 'crypto_balance_id');
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the type label attribute.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'deposit' => 'Депозит',
            'withdrawal' => 'Вывод',
            'transfer' => 'Перевод',
            'reserve' => 'Резервирование',
            'release' => 'Освобождение',
            'fee' => 'Комиссия',
            'refund' => 'Возврат',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Scope a query to only include transactions of a given type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include transactions of a given status.
     */
    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include confirmed transactions.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('confirmations', '>', 0);
    }

    /**
     * Check if transaction is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->confirmations > 0;
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
