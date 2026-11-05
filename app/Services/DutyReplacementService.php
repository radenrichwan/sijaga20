<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DutyReplacement;
use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DutyReplacementService
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    /**
     * @param  array{duty_schedule_id: int, original_user_id: int, replacement_user_id: int, reason: string}  $data
     */
    public function request(array $data, User $actor): DutyReplacement
    {
        return DB::transaction(function () use ($data, $actor): DutyReplacement {
            $schedule = DutySchedule::query()->whereKey($data['duty_schedule_id'])->lockForUpdate()->firstOrFail();
            $original = User::query()->whereKey($data['original_user_id'])->firstOrFail();
            $replacement = User::query()->whereKey($data['replacement_user_id'])->firstOrFail();

            abort_unless($schedule->members()->whereKey($original->id)->exists(), 422, 'Anggota awal tidak terdaftar pada jadwal tersebut.');
            abort_if($original->is($replacement), 422, 'Anggota pengganti harus berbeda dari anggota awal.');
            abort_unless($original->is($actor) || $actor->canManageSchedule(), 403, 'Pengajuan hanya dapat dibuat oleh anggota yang bersangkutan atau pengelola jadwal.');

            $this->assertReplacementWindow($schedule);
            $this->assertActiveOperational($replacement);

            if ($schedule->members()->whereKey($replacement->id)->exists()) {
                throw ValidationException::withMessages([
                    'replacement_user_id' => 'Pengganti sudah bertugas pada tanggal tersebut.',
                ]);
            }

            $hasPendingOrApproved = DutyReplacement::query()
                ->where('duty_schedule_id', $schedule->id)
                ->where('original_user_id', $original->id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();
            if ($hasPendingOrApproved) {
                throw ValidationException::withMessages([
                    'original_user_id' => 'Anggota ini sudah memiliki pengajuan penggantian aktif pada jadwal tersebut.',
                ]);
            }

            $replacementRequest = DutyReplacement::query()->create([
                ...$data,
                'requested_by' => $actor->id,
                'status' => 'pending',
            ]);
            $this->activityLogs->record($actor, 'create', 'duty_replacement', $replacementRequest, null, [
                'duty_schedule_id' => $schedule->id,
                'original_user_id' => $original->id,
                'replacement_user_id' => $replacement->id,
                'status' => 'pending',
            ]);

            return $replacementRequest;
        });
    }

    public function decide(DutyReplacement $replacement, User $actor, string $status, ?string $reason = null): DutyReplacement
    {
        return DB::transaction(function () use ($replacement, $actor, $status, $reason): DutyReplacement {
            $replacement = DutyReplacement::query()->whereKey($replacement->id)->lockForUpdate()->firstOrFail();
            if ($replacement->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan berstatus pending yang dapat diputuskan.',
                ]);
            }

            if ($status === 'approved') {
                $schedule = DutySchedule::query()->whereKey($replacement->duty_schedule_id)->lockForUpdate()->firstOrFail();
                $this->assertActiveOperational($replacement->replacementUser);
                if (! $replacement->replacementStudent?->is_active) {
                    throw ValidationException::withMessages([
                        'replacement_student_id' => 'Akun siswa pengganti harus masih aktif.',
                    ]);
                }
                if ($schedule->members()->whereKey($replacement->replacement_user_id)->exists()) {
                    throw ValidationException::withMessages([
                        'replacement_user_id' => 'Pengganti sudah bertugas pada tanggal tersebut.',
                    ]);
                }
                $otherApprovedReplacement = DutyReplacement::query()
                    ->where('duty_schedule_id', $schedule->id)
                    ->where('replacement_user_id', $replacement->replacement_user_id)
                    ->where('status', 'approved')
                    ->where('id', '<>', $replacement->id)
                    ->exists();
                if ($otherApprovedReplacement) {
                    throw ValidationException::withMessages([
                        'replacement_user_id' => 'Anggota pengganti sudah menggantikan anggota lain pada tanggal tersebut.',
                    ]);
                }
            }

            $oldValues = $replacement->only(['status', 'rejection_reason', 'decided_by', 'decided_at']);
            $replacement->forceFill([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $reason : null,
                'decided_by' => $actor->id,
                'decided_at' => now('Asia/Jakarta'),
            ])->save();

            $this->activityLogs->record(
                $actor,
                $status === 'approved' ? 'approve' : 'reject',
                'duty_replacement',
                $replacement,
                $oldValues,
                ['status' => $status, 'decision_reason' => $reason],
            );
            $this->notifications->send(
                collect([$replacement->requestedBy, $replacement->originalUser, $replacement->replacementUser]),
                'duty_replacement.'.$status,
                $status === 'approved' ? 'Penggantian piket disetujui' : 'Penggantian piket ditolak',
                $status === 'approved'
                    ? 'Pengajuan penggantian anggota piket Anda disetujui.'
                    : 'Pengajuan penggantian anggota piket Anda ditolak: '.($reason ?? ''),
                route('duty-replacements.index'),
                ['duty_replacement_id' => $replacement->id, 'status' => $status],
            );

            return $replacement;
        });
    }

    private function assertReplacementWindow(DutySchedule $schedule): void
    {
        $dutyDate = Carbon::parse($schedule->duty_date, 'Asia/Jakarta')->startOfDay();
        $today = now('Asia/Jakarta')->startOfDay();
        if ($dutyDate->lt($today) || ($dutyDate->isSameDay($today) && now('Asia/Jakarta')->gte($today->copy()->setTime(6, 0)))) {
            throw ValidationException::withMessages([
                'duty_schedule_id' => 'Penggantian pada hari pelaksanaan hanya dapat diajukan atau disetujui sebelum pukul 06.00 WIB.',
            ]);
        }
    }

    private function assertActiveOperational(User $user): void
    {
        if (! $user->is_active || ! $user->hasRole('operasional')) {
            throw ValidationException::withMessages([
                'replacement_user_id' => 'Pengganti harus merupakan akun operasional yang aktif.',
            ]);
        }
    }
}
