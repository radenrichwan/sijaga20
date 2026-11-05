<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DutyAttendanceService
{
    public function __construct(
        private readonly PointTransactionService $transactions,
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function record(DutySchedule $schedule, User $member, User $actor): DutyAttendance
    {
        return DB::transaction(function () use ($schedule, $member, $actor): DutyAttendance {
            $lockedSchedule = DutySchedule::query()
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedMember = User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $lockedActor = User::query()->whereKey($actor->id)->firstOrFail();

            if (! $lockedActor->isActive() || ! $lockedActor->isOperasional()) {
                abort(403, 'Hanya akun operasional aktif yang dapat mencatat absensi piket.');
            }

            if (! $lockedMember->is_active || ! $lockedMember->isOperasional()) {
                throw ValidationException::withMessages([
                    'member_user_id' => 'Anggota piket harus merupakan akun operasional yang aktif.',
                ]);
            }

            if ($lockedSchedule->status !== 'scheduled'
                || ! $lockedSchedule->duty_date->isSameDay(today('Asia/Jakarta'))) {
                throw ValidationException::withMessages([
                    'duty_schedule_id' => 'Absensi hanya dapat dicatat pada jadwal aktif hari ini.',
                ]);
            }

            $replacement = $lockedSchedule->replacements()
                ->where('replacement_user_id', $lockedMember->id)
                ->where('status', 'approved')
                ->latest('id')
                ->first();

            if ($replacement !== null) {
                if ($lockedSchedule->replacements()
                    ->where('original_user_id', $lockedMember->id)
                    ->where('status', 'approved')
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'member_user_id' => 'Anggota awal yang telah digantikan tidak berhak menerima poin untuk jadwal ini.',
                    ]);
                }

                $studentId = $replacement->replacement_student_id;
                if ($studentId === null) {
                    throw ValidationException::withMessages([
                        'member_user_id' => 'Akun siswa penerima poin untuk pengganti belum ditentukan.',
                    ]);
                }
            } else {
                $membership = $lockedSchedule->members()
                    ->where('users.id', $lockedMember->id)
                    ->first();
                if ($membership === null) {
                    throw ValidationException::withMessages([
                        'member_user_id' => 'Anggota ini bukan anggota jadwal atau pengganti yang disetujui.',
                    ]);
                }

                if ($lockedSchedule->replacements()
                    ->where('original_user_id', $lockedMember->id)
                    ->where('status', 'approved')
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'member_user_id' => 'Anggota awal yang telah digantikan tidak berhak menerima poin untuk jadwal ini.',
                    ]);
                }

                $studentId = $membership->pivot->student_id;
                if ($studentId === null) {
                    throw ValidationException::withMessages([
                        'member_user_id' => 'Akun siswa penerima poin belum ditautkan pada anggota jadwal ini.',
                    ]);
                }
            }

            $lockedStudent = Student::query()
                ->whereKey($studentId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if ($lockedStudent === null) {
                throw ValidationException::withMessages([
                    'member_user_id' => 'Profil siswa penerima poin tidak ditemukan atau tidak aktif.',
                ]);
            }

            if (DutyAttendance::query()
                ->where('duty_schedule_id', $lockedSchedule->id)
                ->where('member_user_id', $lockedMember->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'member_user_id' => 'Anggota ini sudah dicatat hadir untuk jadwal tersebut.',
                ]);
            }

            $attendance = DutyAttendance::query()->create([
                'duty_schedule_id' => $lockedSchedule->id,
                'student_id' => $lockedStudent->id,
                'member_user_id' => $lockedMember->id,
                'recorded_by' => $actor->id,
                'attended_at' => now('Asia/Jakarta'),
            ]);
            $category = ViolationCategory::query()->firstOrCreate(
                ['name' => 'Piket Gerbang', 'type' => 'reward'],
                ['points' => 10, 'description' => 'Poin kehadiran piket gerbang.'],
            );
            $transaction = $this->transactions->record(
                $lockedStudent,
                $category,
                $actor,
                'duty_attendance',
                $attendance->id,
                10,
                'Kehadiran piket gerbang.',
                $attendance->attended_at->toDateString(),
            );
            $attendance->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'create', 'duty_attendance', $attendance, null, [
                'duty_schedule_id' => $lockedSchedule->id,
                'member_user_id' => $lockedMember->id,
                'student_id' => $lockedStudent->id,
                'points' => 10,
            ]);
            $this->notifications->send(
                [$lockedStudent->user],
                'duty_attendance.created',
                'Poin piket gerbang',
                'Kehadiran piket Anda dicatat dan Anda menerima 10 poin.',
                route('students.show', $lockedStudent),
                ['duty_attendance_id' => $attendance->id, 'points' => 10],
            );

            return $attendance;
        });
    }
}
