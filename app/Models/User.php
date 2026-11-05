<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'password',
        'role_id',
        'teacher_type',
        'can_manage_schedule',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_manage_schedule' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->username;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }

    public function isGuru(): bool
    {
        return $this->hasRole('guru');
    }

    public function isSiswa(): bool
    {
        return $this->hasRole('siswa');
    }

    public function isOperasional(): bool
    {
        return $this->hasRole('operasional');
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function recordedPointLedgers(): HasMany
    {
        return $this->hasMany(PointLedger::class, 'recorded_by');
    }

    public function recordedPointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'recorded_by');
    }

    public function recordedViolations(): HasMany
    {
        return $this->hasMany(Violation::class, 'recorded_by');
    }

    public function recordedLatenessRecords(): HasMany
    {
        return $this->hasMany(LatenessRecord::class, 'recorded_by');
    }

    public function recordedMorningRewards(): HasMany
    {
        return $this->hasMany(MorningReward::class, 'recorded_by');
    }

    public function dutySchedules(): BelongsToMany
    {
        return $this->belongsToMany(DutySchedule::class, 'duty_schedule_members')
            ->withPivot('student_id')
            ->withTimestamps();
    }

    public function createdDutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class, 'created_by');
    }

    public function cancelledDutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class, 'cancelled_by');
    }

    public function requestedDutyReplacements(): HasMany
    {
        return $this->hasMany(DutyReplacement::class, 'requested_by');
    }

    public function originalDutyReplacements(): HasMany
    {
        return $this->hasMany(DutyReplacement::class, 'original_user_id');
    }

    public function replacementDutyReplacements(): HasMany
    {
        return $this->hasMany(DutyReplacement::class, 'replacement_user_id');
    }

    public function decidedDutyReplacements(): HasMany
    {
        return $this->hasMany(DutyReplacement::class, 'decided_by');
    }

    public function recordedDutyAttendances(): HasMany
    {
        return $this->hasMany(DutyAttendance::class, 'recorded_by');
    }

    public function givenAdditionalPoints(): HasMany
    {
        return $this->hasMany(AdditionalPoint::class, 'given_by');
    }

    public function decidedStudentObjections(): HasMany
    {
        return $this->hasMany(StudentObjection::class, 'decided_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->hasRole(...Role::NAMES);
    }

    public function canManageSchedule(): bool
    {
        return $this->isActive()
            && ($this->isGuru() || ($this->isOperasional() && $this->can_manage_schedule));
    }
}
