<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\UserRoleEnum;
use App\Models\StudentPaymentTerm;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_DROPPED = 'dropped';

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'password',
        'birthday',
        'address',
        'phone',
        'student_id',
        'profile_picture',
        'course',
        'year_level',
        'faculty',
        'status',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Set the appends property to include virtual attributes
    protected $appends = ['name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
        ];
    }

    // Relationships
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * ✅ NEW: Payment terms relationship
     */
    public function paymentTerms(): HasMany
    {
        return $this->hasManyThrough(
            StudentPaymentTerm::class,
            Student::class,
            'user_id',      // Foreign key on students table
            'account_id',   // Foreign key on payment_terms table
            'id',           // Local key on users table
            'account_id'    // Local key on students table
        );
    }

    /**
     * Get the user's full name.
     * This is the main accessor that will be serialized in API responses.
     */
    public function getNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * Get the user's full name (alternative format).
     * Use this for display purposes where you want "Last, First MI."
     */
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? "{$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name} {$mi}";
    }

    /**
     * Get validation rules for user updates
     */
    public static function getValidationRules($userId = null): array
    {
        return [
            'student_id' => 'nullable|string|unique:users,student_id,' . $userId,
            'address' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:100',
            'year_level' => 'nullable|string|max:50',
            'faculty' => 'nullable|string|max:100',
            'status' => 'required|in:active,graduated,dropped',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get all assessments for this student
     */
    public function assessments(): HasMany
    {
        return $this->hasManyThrough(
            StudentAssessment::class,
            Student::class,
            'user_id',      // Foreign key on students table
            'account_id',   // Foreign key on assessments table
            'id',           // Local key on users table
            'account_id'    // Local key on students table
        );
    }

    /**
     * Get active assessment
     */
    public function activeAssessment(): HasOne
    {
        return $this->hasOneThrough(
            StudentAssessment::class,
            Student::class,
            'user_id',
            'account_id',
            'id',
            'account_id'
        )->where('status', 'active')->latest();
    }

    /**
     * Get role value (handles both string and enum)
     */
    public function getRoleValueAttribute(): string
    {
        return is_object($this->role) ? $this->role->value : (string) $this->role;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role_value, $roles);
        }
        return $this->role_value === $roles;
    }
}