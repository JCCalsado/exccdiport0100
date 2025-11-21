<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    protected $fillable = [
        'program_id',
        'code',
        'title',
        'description',
        'lec_units',
        'lab_units',
        'total_units',
        'has_lab',
        'is_active',
    ];

    protected $casts = [
        'lec_units' => 'decimal:2',
        'lab_units' => 'decimal:2',
        'total_units' => 'decimal:2',
        'has_lab' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function curricula(): BelongsToMany
    {
        return $this->belongsToMany(Curriculum::class, 'curriculum_courses')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('curriculum_courses.order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getFullTitleAttribute(): string
    {
        return "{$this->code} - {$this->title}";
    }
}