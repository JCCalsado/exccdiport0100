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

    // ✅ Boot method to auto-generate account_id
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->account_id)) {
                $student->account_id = self::generateUniqueAccountId();
            }
        });
    }

    // ✅ Relationships using account_id
    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'account_id', 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    public function studentPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'account_id', 'account_id');
    }

    // ✅ Keep existing relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    // ✅ Accessors
    public function getFormattedAccountIdAttribute(): string
    {
        return strtoupper($this->account_id);
    }

    public function getRemainingBalanceAttribute()
    {
        $totalPaid = $this->payments()->sum('amount');
        return $this->total_balance - $totalPaid;
    }

    // ✅ Generate unique account_id
    public static function generateUniqueAccountId(): string
    {
        return DB::transaction(function () {
            $date = date('Ymd');
            $attempts = 0;
            $maxAttempts = 100;

            do {
                $sequential = str_pad(DB::table('students')->whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
                $accountId = "ACC-{$date}-{$sequential}";
                
                $exists = self::where('account_id', $accountId)->lockForUpdate()->exists();
                
                $attempts++;
                
                if ($attempts > $maxAttempts) {
                    // Fallback: use unique timestamp-based ID
                    $accountId = "ACC-{$date}-" . strtoupper(substr(uniqid(), -4));
                    break;
                }
            } while ($exists);

            return $accountId;
        });
    }

    // ✅ Validation rules
    public static function validationRules($id = null): array
    {
        return [
            'account_id' => [
                'required',
                'string',
                'max:50',
                'unique:students,account_id,' . $id,
                'regex:/^ACC-\d{8}-[A-Z0-9]{4}$/',
            ],
            'student_id' => 'nullable|string|unique:students,student_id,' . $id,
            'user_id' => 'required|exists:users,id',
            'email' => 'required|email|unique:students,email,' . $id,
            'course' => 'required|string|max:255',
            'year_level' => 'required|string|in:1st Year,2nd Year,3rd Year,4th Year',
            'status' => 'required|in:enrolled,graduated,inactive',
        ];
    }

    // ✅ Scopes
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'enrolled');
    }
}