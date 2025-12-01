<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',           // Keep for backward compatibility
        'account_id',        // ✅ NEW - Primary identifier
        'fee_id', 
        'reference', 
        'payment_channel', 
        'kind', 
        'type', 
        'year',
        'semester',
        'amount', 
        'status', 
        'paid_at', 
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // ✅ NEW: Relationship via account_id
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    // Keep user relationship for backward compatibility
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    // ✅ NEW: Scope by account_id
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    protected static function booted()
    {
        static::saved(function ($transaction) {
            if ($transaction->wasChanged(['amount', 'status', 'kind'])) {
                app()->terminating(function () use ($transaction) {
                    if ($transaction->user) {
                        AccountService::recalculate($transaction->user);
                    }
                });
            }
        });
    }
}