<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',       // ✅ NEW PRIMARY IDENTIFIER
        'student_id',       // Keep for backward compatibility
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'course',
        'year_level',
        'birthday',
        'phone',
        'address',
        'total_balance',
        'status',
    ];

    protected $casts = [
        'birthday' => 'date',
        'total_balance' => 'decimal:2',
    ];

    /**
     * ✅ Auto-generate account_id on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->account_id)) {
                $student->account_id = self::generateAccountId();
            }
        });
    }

    /**
     * ✅ Generate unique account_id in format ACC-YYYYMMDD-XXXX
     */
    public static function generateAccountId(): string
    {
        return DB::transaction(function () {
            $date = now()->format('Ymd');
            $prefix = "ACC-{$date}-";

            // Find the highest existing number for today
            $lastStudent = self::where('account_id', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                $lastNumber = intval(substr($lastStudent->account_id, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newAccountId = "{$prefix}{$newNumber}";

            // Ensure uniqueness (safety check)
            $attempts = 0;
            while (self::where('account_id', $newAccountId)->exists() && $attempts < 100) {
                $lastNumber = intval($newNumber);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newAccountId = "{$prefix}{$newNumber}";
                $attempts++;
            }

            if ($attempts >= 100) {
                throw new \Exception('Unable to generate unique account_id after 100 attempts');
            }

            return $newAccountId;
        });
    }

    /**
     * ✅ Validate account_id format
     */
    public static function isValidAccountId(string $accountId): bool
    {
        return (bool) preg_match('/^ACC-\d{8}-\d{4}$/', $accountId);
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ✅ NEW: Payments using account_id
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'account_id', 'account_id');
    }

    /**
     * ✅ NEW: Transactions using account_id
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    /**
     * ✅ NEW: Payment terms using account_id
     */
    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    /**
     * ✅ NEW: Assessments using account_id
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'account_id', 'account_id');
    }

    /**
     * User's account (for balance)
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    public function getRemainingBalanceAttribute()
    {
        $totalPaid = $this->payments()->sum('amount');
        return $this->total_balance - $totalPaid;
    }

    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? " {$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * ✅ NEW: Find by account_id
     */
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'enrolled');
    }

    public function scopeGraduated($query)
    {
        return $query->where('status', 'graduated');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}