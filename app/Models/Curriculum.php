<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $fillable = [
        'program_id',
        'school_year',
        'year_level',
        'semester',
        'tuition_per_unit',
        'lab_fee',
        'registration_fee',
        'misc_fee',
        'term_count',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'tuition_per_unit' => 'decimal:2',
        'lab_fee' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'misc_fee' => 'decimal:2',
        'term_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'curriculum_courses')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('curriculum_courses.order');
    }

    public function studentCurricula(): HasMany
    {
        return $this->hasMany(StudentCurriculum::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTerm($query, $programId, $yearLevel, $semester, $schoolYear)
    {
        return $query->where('program_id', $programId)
            ->where('year_level', $yearLevel)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear);
    }

    /**
     * Calculate total tuition for this curriculum
     */
    public function calculateTuition(): float
    {
        $totalUnits = $this->courses->sum('total_units');
        return $totalUnits * $this->tuition_per_unit;
    }

    /**
     * Calculate total lab fees
     */
    public function calculateLabFees(): float
    {
        $coursesWithLab = $this->courses->where('has_lab', true)->count();
        return $coursesWithLab * $this->lab_fee;
    }

    /**
     * Calculate total assessment
     */
    public function calculateTotalAssessment(): float
    {
        return $this->calculateTuition() 
            + $this->calculateLabFees() 
            + $this->registration_fee 
            + $this->misc_fee;
    }

    /**
     * Generate payment terms breakdown
     */
    public function generatePaymentTerms(): array
    {
        $total = $this->calculateTotalAssessment();
        $perTerm = round($total / $this->term_count, 2);
        
        return [
            'upon_registration' => $perTerm,
            'prelim' => $perTerm,
            'midterm' => $perTerm,
            'semi_final' => $perTerm,
            'final' => $total - ($perTerm * 4),
        ];
    }

    public function getTermDescriptionAttribute(): string
    {
        return "{$this->year_level} - {$this->semester} ({$this->school_year})";
    }
}