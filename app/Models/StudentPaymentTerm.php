<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPaymentTerm extends Model
{
    protected $fillable = [
        'user_id',        // Keep for backward compatibility
        'account_id',     // ✅ NEW - Primary identifier
        'curriculum_id',
        'school_year',
        'semester',
        'term_name',
        'term_order',
        'amount',
        'due_date',
        'status',
        'paid_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
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

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return (float) ($this->amount - $this->paid_amount);
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->amount;
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid')
                     ->whereRaw('paid_amount < amount');
    }

    public function scopeForTerm($query, $schoolYear, $semester)
    {
        return $query->where('school_year', $schoolYear)
                     ->where('semester', $semester);
    }

    // ✅ NEW: Scope by account_id
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}