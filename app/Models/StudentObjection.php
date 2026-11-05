<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StudentObjection extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'source_type',
        'source_id',
        'reason',
        'supporting_note',
        'evidence_path',
        'status',
        'decision_reason',
        'decided_by',
        'decided_at',
        'correction_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function correctionTransaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'correction_transaction_id');
    }
}
