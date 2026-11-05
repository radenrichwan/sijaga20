<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'category_id',
        'recorded_by',
        'type',
        'points',
        'description',
        'transaction_date',
        'source_type',
        'source_id',
        'counter_entry_for',
        'is_active',
        'transacted_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'transaction_date' => 'date',
            'is_active' => 'boolean',
            'transacted_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ViolationCategory::class, 'category_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function counterEntryFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'counter_entry_for');
    }

    public function counterEntries(): HasMany
    {
        return $this->hasMany(self::class, 'counter_entry_for');
    }
}
