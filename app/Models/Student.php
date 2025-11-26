<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // ✅ FIX: Added 'user_id' to fillable array
    protected $fillable = [
        'user_id',  // ← THIS WAS MISSING!
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // transactions via the linked user (transactions.user_id == students.user_id)
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    // Calculate remaining balance
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