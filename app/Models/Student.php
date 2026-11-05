<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nis',
        'nisn',
        'gender',
        'grade',
        'major',
        'class_name',
        'is_active',
        'initial_points',
        'current_points',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'initial_points' => 'integer',
            'current_points' => 'integer',
        ];
    }

    public function getCurrentPointsAttribute(mixed $value): int
    {
        $attributes = $this->getAttributes();
        $transactionPoints = array_key_exists('ledger_points_sum', $attributes)
            ? (int) $attributes['ledger_points_sum']
            : (int) $this->pointTransactions()->where('is_active', true)->sum('points');

        return (int) ($attributes['initial_points'] ?? 0) + $transactionPoints;
    }

    public function scopeWithCalculatedPoints(Builder $query): Builder
    {
        return $query->withSum([
            'pointTransactions as ledger_points_sum' => fn (Builder $transactions) => $transactions->where('is_active', true),
        ], 'points');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointLedgers(): HasMany
    {
        return $this->hasMany(PointLedger::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    public function latenessRecords(): HasMany
    {
        return $this->hasMany(LatenessRecord::class);
    }

    public function morningRewards(): HasMany
    {
        return $this->hasMany(MorningReward::class);
    }

    public function dutyAttendances(): HasMany
    {
        return $this->hasMany(DutyAttendance::class);
    }

    public function additionalPoints(): HasMany
    {
        return $this->hasMany(AdditionalPoint::class);
    }

    public function objections(): HasMany
    {
        return $this->hasMany(StudentObjection::class);
    }
}
