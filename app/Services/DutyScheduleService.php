<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DutySchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DutyScheduleService
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    /**
     * @return array<string, list<int>>
     */
    public function generate(Carbon $startDate, Carbon $endDate): array
    {
        $members = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'operasional'))
            ->where('is_active', true)
            ->get()
            ->shuffle();

        if ($members->count() < 4) {
            throw ValidationException::withMessages([
                'schedule' => 'Minimal empat akun operasional aktif diperlukan untuk membuat jadwal.',
            ]);
        }

        $dates = $this->weekdaysBetween($startDate, $endDate);
        $preview = [];

        foreach ($dates->groupBy(fn (Carbon $date): string => $date->copy()->startOfWeek()->toDateString()) as $weekDates) {
            $counts = $members->mapWithKeys(fn (User $member): array => [$member->id => 0])->all();

            foreach ($weekDates as $date) {
                $tieBreakers = $members->shuffle()->values()->mapWithKeys(
                    fn (User $member, int $index): array => [$member->id => $index],
                );
                $selected = $members
                    ->sort(function (User $left, User $right) use ($counts, $tieBreakers): int {
                        return [$counts[$left->id], $tieBreakers[$left->id]]
                            <=> [$counts[$right->id], $tieBreakers[$right->id]];
                    })
                    ->take(4)
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all();

                foreach ($selected as $memberId) {
                    $counts[$memberId]++;
                }

                $preview[$date->toDateString()] = $selected;
            }
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return Collection<string, Collection<int, int>>
     */
    public function validateSchedule(array $schedule, Carbon $startDate, Carbon $endDate): Collection
    {
        $expectedDates = $this->weekdaysBetween($startDate, $endDate)->map(
            fn (Carbon $date): string => $date->toDateString(),
        );
        $submittedDates = collect(array_keys($schedule))->sort()->values();

        if ($expectedDates->sort()->values()->all() !== $submittedDates->all()) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal harus mencakup setiap hari kerja dalam periode dan tidak boleh memuat Sabtu atau Minggu.',
            ]);
        }

        $memberIds = collect($schedule)->flatten()->map(fn ($id): int => (int) $id);
        if ($memberIds->contains(0)) {
            throw ValidationException::withMessages([
                'schedule' => 'Pilihan anggota tidak valid.',
            ]);
        }

        $activeOperationalIds = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'operasional'))
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        if (! $memberIds->unique()->every(fn (int $id): bool => in_array($id, $activeOperationalIds, true))) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal hanya dapat berisi akun operasional yang masih aktif.',
            ]);
        }

        $normalized = collect($schedule)->map(function ($members, string $date): Collection {
            if (! is_array($members)) {
                throw ValidationException::withMessages(['schedule' => 'Daftar anggota pada setiap hari tidak valid.']);
            }

            $ids = collect($members)->map(fn ($id): int => (int) $id);
            if ($ids->count() < 4 || $ids->count() > 6 || $ids->count() !== $ids->unique()->count()) {
                throw ValidationException::withMessages([
                    'schedule' => "Jadwal {$date} harus berisi 4–6 anggota yang berbeda.",
                ]);
            }

            return $ids->values();
        });

        foreach ($expectedDates->groupBy(fn (string $date): string => Carbon::parse($date)->startOfWeek()->toDateString()) as $weekDates) {
            $counts = collect($activeOperationalIds)->mapWithKeys(fn (int $id): array => [$id => 0]);
            foreach ($weekDates as $date) {
                foreach ($normalized->get($date) as $memberId) {
                    $counts->put($memberId, $counts->get($memberId, 0) + 1);
                }
            }
            if ($counts->max() - $counts->min() > 1) {
                throw ValidationException::withMessages([
                    'schedule' => 'Selisih jumlah hari bertugas setiap anggota dalam satu minggu maksimal satu hari.',
                ]);
            }
        }

        return $normalized;
    }

    /**
     * @param  Collection<string, Collection<int, int>>  $schedule
     */
    public function confirm(Collection $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $savedSchedules = collect();
            foreach ($schedule as $date => $memberIds) {
                $monday = Carbon::parse($date)->startOfWeek()->toDateString();
                $dutySchedule = DutySchedule::query()
                    ->whereDate('duty_date', $date)
                    ->lockForUpdate()
                    ->first();
                $oldMemberIds = $dutySchedule?->members()->pluck('users.id')->sort()->values() ?? collect();
                $newMemberIds = $memberIds->map(fn (int $id): int => $id)->sort()->values();
                $rosterChanged = $oldMemberIds->all() !== $newMemberIds->all();

                if ($dutySchedule !== null && $rosterChanged
                    && ($dutySchedule->attendances()->exists() || $dutySchedule->replacements()->exists())) {
                    throw ValidationException::withMessages([
                        'schedule' => "Jadwal {$date} tidak dapat mengubah anggota karena sudah memiliki absensi atau penggantian.",
                    ]);
                }

                $wasRecentlyCreated = $dutySchedule === null;
                $wasCancelled = $dutySchedule?->status === 'cancelled';
                $dutySchedule = DutySchedule::query()->updateOrCreate([
                    'duty_date' => Carbon::parse($date),
                ], [
                    'week_start' => $monday,
                    'status' => 'scheduled',
                    'cancellation_reason' => null,
                    'cancelled_by' => null,
                    'cancelled_at' => null,
                    'created_by' => $dutySchedule?->created_by ?? $actor->id,
                ]);
                $dutySchedule->members()->sync($memberIds->all());
                $savedSchedules->push($dutySchedule);

                if ($wasRecentlyCreated || $rosterChanged || $wasCancelled) {
                    $this->activityLogs->record(
                        $actor,
                        $wasRecentlyCreated ? 'create' : 'update',
                        'duty_schedule',
                        $dutySchedule,
                        $wasRecentlyCreated ? null : [
                            'duty_date' => $date,
                            'member_user_ids' => $oldMemberIds->all(),
                        ],
                        [
                            'duty_date' => $date,
                            'week_start' => $monday,
                            'member_user_ids' => $memberIds->all(),
                        ],
                    );

                    $members = User::query()->whereIn('id', $memberIds)->get();
                    $this->notifications->send(
                        $members,
                        'duty_schedule.released',
                        'Jadwal piket baru',
                        'Jadwal piket gerbang tanggal '.Carbon::parse($date)->translatedFormat('d F Y').' telah dirilis.',
                        route('duty-schedules.index', ['period' => 'week', 'start_date' => $date]),
                        ['duty_schedule_id' => $dutySchedule->id, 'duty_date' => $date],
                    );
                }
            }

            $this->ensureMemberStudentLinks($savedSchedules);
        });
    }

    /**
     * Fill missing or unusable student links for active operational members.
     *
     * @param  Collection<int, DutySchedule>  $schedules
     */
    public function ensureMemberStudentLinks(Collection $schedules): void
    {
        $scheduleIds = $schedules->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();
        if ($scheduleIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($scheduleIds): void {
            $members = DB::table('duty_schedule_members as membership')
                ->join('duty_schedules as schedule', 'schedule.id', '=', 'membership.duty_schedule_id')
                ->join('users', 'users.id', '=', 'membership.user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->whereIn('membership.duty_schedule_id', $scheduleIds)
                ->orderBy('schedule.duty_date')
                ->orderBy('membership.id')
                ->lockForUpdate()
                ->get([
                    'membership.id as membership_id',
                    'membership.duty_schedule_id',
                    'membership.user_id',
                    'membership.student_id',
                    'users.is_active as user_is_active',
                    'roles.name as role_name',
                ]);

            $activeStudents = Student::query()
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->pluck('id')
                ->map(fn (int $id): int => $id)
                ->shuffle()
                ->values();
            $activeStudentIds = $activeStudents->flip();

            $studentByMember = DB::table('duty_schedule_members as membership')
                ->join('duty_schedules as schedule', 'schedule.id', '=', 'membership.duty_schedule_id')
                ->join('users', 'users.id', '=', 'membership.user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->join('students', 'students.id', '=', 'membership.student_id')
                ->join('users as student_user', 'student_user.id', '=', 'students.user_id')
                ->where('roles.name', 'operasional')
                ->where('users.is_active', true)
                ->where('students.is_active', true)
                ->where('student_user.is_active', true)
                ->orderBy('schedule.duty_date')
                ->orderBy('membership.id')
                ->get(['membership.user_id', 'membership.student_id'])
                ->unique('user_id')
                ->mapWithKeys(fn (object $row): array => [(int) $row->user_id => (int) $row->student_id]);

            $attendedStudentByMember = DB::table('duty_attendances')
                ->whereIn('duty_schedule_id', $scheduleIds)
                ->get(['duty_schedule_id', 'member_user_id', 'student_id'])
                ->mapWithKeys(fn (object $row): array => [
                    $row->duty_schedule_id.':'.$row->member_user_id => (int) $row->student_id,
                ]);
            $usedBySchedule = [];
            foreach ($attendedStudentByMember as $key => $studentId) {
                [$scheduleId] = explode(':', $key, 2);
                $usedBySchedule[(int) $scheduleId][] = $studentId;
            }
            $needsLink = $members->contains(
                function (object $member) use ($attendedStudentByMember, $activeStudentIds): bool {
                    $attendanceKey = $member->duty_schedule_id.':'.$member->user_id;

                    return $member->role_name === 'operasional'
                        && (bool) $member->user_is_active
                        && ! $attendedStudentByMember->has($attendanceKey)
                        && ($member->student_id === null || ! $activeStudentIds->has((int) $member->student_id));
                },
            );

            if ($needsLink && $activeStudents->isEmpty()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Tidak ada siswa aktif yang dapat ditautkan ke anggota piket.',
                ]);
            }

            foreach ($members as $member) {
                $scheduleId = (int) $member->duty_schedule_id;
                $usedBySchedule[$scheduleId] ??= [];
                $currentStudentId = $member->student_id === null ? null : (int) $member->student_id;
                if ($member->role_name !== 'operasional' || ! $member->user_is_active) {
                    if ($currentStudentId !== null) {
                        $usedBySchedule[$scheduleId][] = $currentStudentId;
                    }

                    continue;
                }

                $attendanceKey = $scheduleId.':'.$member->user_id;
                $attendedStudentId = $attendedStudentByMember->get($attendanceKey);
                $currentIsActive = $currentStudentId !== null && $activeStudentIds->has($currentStudentId);
                $currentAlreadyUsed = $currentStudentId !== null
                    && in_array($currentStudentId, $usedBySchedule[$scheduleId], true);

                if ($attendedStudentId !== null) {
                    $studentId = $attendedStudentId;
                } elseif ($currentIsActive && ! $currentAlreadyUsed) {
                    $studentId = $currentStudentId;
                } else {
                    $studentId = $studentByMember->get((int) $member->user_id);
                    if ($studentId === null
                        || ! $activeStudentIds->has($studentId)
                        || in_array($studentId, $usedBySchedule[$scheduleId], true)) {
                        $studentId = $activeStudents
                            ->first(fn (int $candidate): bool => ! in_array(
                                $candidate,
                                $usedBySchedule[$scheduleId],
                                true,
                            ));
                    }

                    if ($studentId === null) {
                        throw ValidationException::withMessages([
                            'schedule' => 'Jumlah siswa aktif harus mencukupi untuk menautkan setiap anggota piket.',
                        ]);
                    }

                    $studentByMember->put((int) $member->user_id, $studentId);
                }

                if ($currentStudentId !== $studentId) {
                    DB::table('duty_schedule_members')
                        ->where('id', $member->membership_id)
                        ->update([
                            'student_id' => $studentId,
                            'updated_at' => now(),
                        ]);
                }

                if ($attendedStudentId === null) {
                    $usedBySchedule[$scheduleId][] = $studentId;
                }
            }
        });
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function weekdaysBetween(Carbon $startDate, Carbon $endDate): Collection
    {
        $dates = collect();
        $cursor = $startDate->copy()->startOfDay();
        while ($cursor->lte($endDate)) {
            if ($cursor->isWeekday()) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        return $dates;
    }
}
