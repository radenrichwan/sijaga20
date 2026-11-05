<?php

declare(strict_types=1);

namespace Tests\Feature\Batch8;

use App\Models\DutyAttendance;
use App\Models\DutyReplacement;
use App\Models\DutySchedule;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DutyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_member_attendance_awards_ten_points_once_and_records_both_operators(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 05:45:00', 'Asia/Jakarta'));
        [$recorder, $member, $student] = $this->createDutyFixture();
        $schedule = $this->createSchedule($recorder, [[$member, $student]]);

        $this->actingAs($recorder)->get(route('duty-attendances.index'))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($student->user->name);

        $payload = ['duty_schedule_id' => $schedule->id, 'member_user_id' => $member->id];
        $this->post(route('duty-attendances.store'), $payload)
            ->assertRedirect(route('duty-attendances.index'));

        $attendance = DutyAttendance::query()->firstOrFail();
        $this->assertSame($member->id, $attendance->member_user_id);
        $this->assertSame($recorder->id, $attendance->recorded_by);
        $this->assertSame($student->id, $attendance->student_id);
        $this->assertSame(10, $attendance->pointTransaction->points);
        $this->assertSame(110, $student->fresh()->current_points);
        $this->assertDatabaseHas('point_transactions', [
            'source_type' => 'duty_attendance',
            'source_id' => $attendance->id,
            'points' => 10,
        ]);

        $this->from(route('duty-attendances.index'))
            ->post(route('duty-attendances.store'), $payload)
            ->assertSessionHasErrors('member_user_id');
        $this->assertSame(1, DutyAttendance::query()->count());
        $this->assertSame(1, PointTransaction::query()->count());
    }

    public function test_only_scheduled_members_and_approved_replacements_can_receive_attendance_points(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 05:30:00', 'Asia/Jakarta'));
        [$recorder, $original, $originalStudent] = $this->createDutyFixture();
        $replacement = $this->operationalUser('piket004');
        $replacementStudent = $this->student('12349');
        $notScheduled = $this->operationalUser('piket005');
        $schedule = $this->createSchedule($recorder, [[$original, $originalStudent]]);
        $this->createScheduleReplacement($schedule, $original, $replacement, $replacementStudent, $recorder, 'approved');

        $payload = ['duty_schedule_id' => $schedule->id, 'member_user_id' => $original->id];
        $this->actingAs($recorder)
            ->from(route('duty-attendances.index'))
            ->post(route('duty-attendances.store'), $payload)
            ->assertSessionHasErrors('member_user_id');
        $this->assertSame(100, $originalStudent->fresh()->current_points);

        $this->post(route('duty-attendances.store'), [
            'duty_schedule_id' => $schedule->id,
            'member_user_id' => $notScheduled->id,
        ])->assertSessionHasErrors('member_user_id');

        $this->post(route('duty-attendances.store'), [
            'duty_schedule_id' => $schedule->id,
            'member_user_id' => $replacement->id,
        ])->assertRedirect(route('duty-attendances.index'));

        $this->assertSame(100, $originalStudent->fresh()->current_points);
        $this->assertSame(110, $replacementStudent->fresh()->current_points);
        $this->assertDatabaseHas('duty_attendances', [
            'duty_schedule_id' => $schedule->id,
            'member_user_id' => $replacement->id,
            'student_id' => $replacementStudent->id,
        ]);
    }

    public function test_pending_or_rejected_replacements_are_not_eligible_for_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 05:30:00', 'Asia/Jakarta'));
        [$recorder, $original, $student] = $this->createDutyFixture();
        $replacement = $this->operationalUser('piket006');
        $replacementStudent = $this->student('12350');
        $schedule = $this->createSchedule($recorder, [[$original, $student]]);
        $this->createScheduleReplacement($schedule, $original, $replacement, $replacementStudent, $recorder, 'pending');

        $this->actingAs($recorder)
            ->from(route('duty-attendances.index'))
            ->post(route('duty-attendances.store'), [
                'duty_schedule_id' => $schedule->id,
                'member_user_id' => $replacement->id,
            ])->assertSessionHasErrors('member_user_id');

        $this->assertSame(100, $replacementStudent->fresh()->current_points);
        $this->assertSame(0, DutyAttendance::query()->count());
    }

    public function test_attendance_is_restricted_to_active_operational_recorders_and_daily_monthly_yearly_reports_work(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 05:30:00', 'Asia/Jakarta'));
        [$recorder, $member, $student] = $this->createDutyFixture();
        $schedule = $this->createSchedule($recorder, [[$member, $student]]);

        $teacher = User::factory()->create();
        $this->actingAs($teacher)->post(route('duty-attendances.store'), [
            'duty_schedule_id' => $schedule->id,
            'member_user_id' => $member->id,
        ])->assertForbidden();

        $this->actingAs($recorder)->post(route('duty-attendances.store'), [
            'duty_schedule_id' => $schedule->id,
            'member_user_id' => $member->id,
        ])->assertRedirect(route('duty-attendances.index'));

        foreach (['day', 'month', 'year'] as $period) {
            $this->get(route('duty-attendances.index', [
                'period' => $period,
                'date' => '2026-11-02',
            ]))->assertOk()->assertSee('Rekapitulasi Kehadiran')->assertSee('10 poin');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{User, User, Student}
     */
    private function createDutyFixture(): array
    {
        $recorder = $this->operationalUser('piket001');
        $member = $this->operationalUser('piket002');
        $student = $this->student('12345');

        return [$recorder, $member, $student];
    }

    private function operationalUser(string $username): User
    {
        return User::factory()->create([
            'username' => $username,
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
            'is_active' => true,
        ]);
    }

    private function student(string $nis): Student
    {
        $user = User::factory()->create([
            'username' => $nis,
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);

        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $nis,
            'nisn' => str_pad((string) (3000000000 + $user->id), 10, '0', STR_PAD_LEFT),
            'class_name' => 'XI RPL 1',
            'is_active' => true,
            'initial_points' => 100,
            'current_points' => 100,
        ]);
    }

    /**
     * @param  array<array{User, Student}>  $members
     */
    private function createSchedule(User $creator, array $members): DutySchedule
    {
        $schedule = DutySchedule::query()->create([
            'week_start' => today('Asia/Jakarta')->startOfWeek()->toDateString(),
            'duty_date' => today('Asia/Jakarta')->toDateString(),
            'status' => 'scheduled',
            'created_by' => $creator->id,
        ]);
        foreach ($members as [$user, $student]) {
            $schedule->members()->attach($user->id, ['student_id' => $student->id]);
        }

        return $schedule;
    }

    private function createScheduleReplacement(
        DutySchedule $schedule,
        User $original,
        User $replacement,
        Student $replacementStudent,
        User $actor,
        string $status,
    ): DutyReplacement {
        return DutyReplacement::query()->create([
            'duty_schedule_id' => $schedule->id,
            'original_user_id' => $original->id,
            'replacement_user_id' => $replacement->id,
            'replacement_student_id' => $replacementStudent->id,
            'requested_by' => $original->id,
            'reason' => 'Berhalangan hadir.',
            'status' => $status,
            'decided_by' => $status === 'pending' ? null : $actor->id,
            'decided_at' => $status === 'pending' ? null : now('Asia/Jakarta'),
        ]);
    }
}
