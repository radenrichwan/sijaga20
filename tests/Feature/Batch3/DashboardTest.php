<?php

declare(strict_types=1);

namespace Tests\Feature\Batch3;

use App\Models\Category;
use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\MorningReward;
use App\Models\PointLedger;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_actual_student_and_monthly_ledger_statistics(): void
    {
        $admin = User::factory()->create();
        $student = $this->createStudent('3012345678', 'XI RPL 1');
        $violation = Category::query()->create(['name' => 'Terlambat', 'type' => 'violation', 'points' => 5]);
        $reward = Category::query()->create(['name' => 'Prestasi', 'type' => 'reward', 'points' => 10]);

        $this->createLedger($student, $admin, $violation, 'violation', -5);
        $this->createLedger($student, $admin, $reward, 'reward', 10);
        Violation::query()->create([
            'student_id' => $student->id,
            'violation_category_id' => $violation->id,
            'recorded_by' => $admin->id,
            'violation_date' => today()->toDateString(),
            'description' => 'Terlambat.',
        ]);
        MorningReward::query()->create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'reward_date' => today()->toDateString(),
            'arrival_time' => '05:10',
            'rank' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total siswa aktif')
            ->assertSee('1')
            ->assertSee('Pelanggaran')
            ->assertSee('Keterlambatan')
            ->assertSee('Penghargaan Pagi')
            ->assertSee('Total saldo poin siswa')
            ->assertSee('teacher-category-config');
    }

    public function test_teacher_can_open_dashboard_and_student_sees_only_personal_summary(): void
    {
        $teacher = User::factory()->create([
            'role_id' => Role::query()->where('name', 'guru')->valueOrFail('id'),
        ]);
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Dashboard Guru');

        $studentUser = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        $ownStudent = $this->createStudent('4012345678', 'X TKJ 1', $studentUser);
        $otherStudent = $this->createStudent('5012345678', 'XI TKJ 2');

        $this->actingAs($studentUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan poin saya')
            ->assertSee($ownStudent->nisn)
            ->assertDontSee($otherStudent->nisn)
            ->assertDontSee('Total siswa');
    }

    public function test_operational_dashboard_shows_today_schedule_attendance_and_morning_reward_form(): void
    {
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $student = $this->createStudent('6012345678', 'XI RPL 1');
        $schedule = DutySchedule::query()->create([
            'week_start' => today()->startOfWeek()->toDateString(),
            'duty_date' => today()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);
        $schedule->members()->attach($operational->id, ['student_id' => $student->id]);
        DutyAttendance::query()->create([
            'duty_schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'recorded_by' => $operational->id,
            'attended_at' => now(),
        ]);

        $this->actingAs($operational)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Operasional')
            ->assertSee('Jadwal piket hari ini')
            ->assertSee($student->user->name)
            ->assertSee('Hadir')
            ->assertSee('morning-award-form');
    }

    public function test_teacher_and_operational_users_can_record_morning_rewards_atomically(): void
    {
        $category = Category::query()->create([
            'name' => 'Reward Pagi',
            'type' => 'reward',
            'points' => 15,
        ]);
        $teacher = User::factory()->create();
        $student = $this->createStudent('7012345678', 'X RPL 1');

        $this->actingAs($teacher)->post(route('morning-rewards.store'), [
            'student_id' => $student->id,
            'reward_date' => today()->toDateString(),
            'arrival_time' => '05:15',
            'rank' => 1,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('morning_rewards', [
            'student_id' => $student->id,
            'rank' => 1,
        ]);
        $this->assertDatabaseHas('point_transactions', [
            'student_id' => $student->id,
            'category_id' => $category->id,
            'points' => 15,
            'source_type' => 'morning_reward',
        ]);
        $this->assertSame(115, $student->refresh()->current_points);
    }

    public function test_teacher_dashboard_applies_date_range_filter_to_operational_statistics(): void
    {
        $teacher = User::factory()->create();
        $student = $this->createStudent('8012345678', 'XI RPL 1');
        $category = Category::query()->create([
            'name' => 'Terlambat',
            'type' => 'violation',
            'points' => 5,
        ]);
        Violation::query()->create([
            'student_id' => $student->id,
            'violation_category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'violation_date' => today()->subDay()->toDateString(),
        ]);

        $this->actingAs($teacher)
            ->get(route('dashboard', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Pelanggaran')
            ->assertSee('Belum ada data pelanggaran pada periode ini.');

        $this->get(route('dashboard', [
            'start_date' => today()->toDateString(),
            'end_date' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('end_date');
    }

    private function createStudent(string $nisn, string $className, ?User $user = null): Student
    {
        $user ??= User::factory()->create();

        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $user->username,
            'nisn' => $nisn,
            'class_name' => $className,
            'initial_points' => 100,
            'current_points' => 100,
        ]);
    }

    private function createLedger(Student $student, User $recorder, Category $category, string $type, int $points): PointLedger
    {
        return PointLedger::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $recorder->id,
            'type' => $type,
            'points' => $points,
            'transaction_date' => today()->toDateString(),
        ]);
    }
}
