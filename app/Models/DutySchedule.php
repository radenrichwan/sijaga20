<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DutySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_start',
        'duty_date',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'duty_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'duty_schedule_members')
            ->withPivot('student_id')
            ->withTimestamps();
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(DutyReplacement::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DutyAttendance::class);
    }
}
