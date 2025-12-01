<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Payment extends Model
{
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'student_id',      // Keep for backward compatibility
        'account_id',      // ✅ NEW - Primary identifier
        'amount', 
        'description', 
        'payment_method', 
        'reference_number', 
        'status', 
        'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // ✅ NEW: Relationship via account_id
    public function studentByAccount(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    // Keep student relationship for backward compatibility
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // ✅ NEW: Scope by account_id
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    protected static function booted()
    {
        static::saved(function ($payment) {
            if ($payment->account_id) {
                $student = Student::where('account_id', $payment->account_id)->first();
                if ($student && $student->user) {
                    AccountService::recalculate($student->user);
                }
            }
        });
    }
}