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
        'account_id',  // ✅ NEW
        'student_id', 
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

    // ✅ NEW: Boot method to auto-generate account_id
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->account_id)) {
                $student->account_id = self::generateAccountId();
            }
        });
    }

    // ✅ NEW: Generate unique account_id
    public static function generateAccountId(): string
    {
        return DB::transaction(function () {
            do {
                $date = now()->format('Ymd');
                $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $accountId = "ACC-{$date}-{$random}";
            } while (self::where('account_id', $accountId)->lockForUpdate()->exists());

            return $accountId;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'account_id', 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'account_id', 'account_id');
    }

    public function getRemainingBalanceAttribute()
    {
        $totalPaid = $this->payments()->sum('amount');
        return $this->total_balance - $totalPaid;
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }
}