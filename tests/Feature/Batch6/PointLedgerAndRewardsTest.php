<?php

declare(strict_types=1);

namespace Tests\Feature\Batch6;

use App\Models\AdditionalPoint;
use App\Models\DutySchedule;
use App\Models\MorningReward;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use App\Services\DutyAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointLedgerAndRewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_morning_reward_crud_uses_fixed_points_and_append_only_corrections(): void
    {
        [$teacher, $students] = $this->createFixtures(3);
        $category = ViolationCategory::query()->create([
            'name' => 'Reward Pagi',
            'type' => 'reward',
            'points' => 15,
        ]);
        $payload = [
            'student_id' => $students[0]->id,
            'reward_date' => today()->toDateString(),
            'arrival_time' => '05:15',
            'rank' => 1,
        ];

        $this->actingAs($teacher)
            ->post(route('morning-rewards.store'), $payload)
            ->assertRedirect(route('dashboard'));
        $reward = MorningReward::query()->firstOrFail();
        $original = $reward->pointTransaction;
        $this->assertSame(15, $original->points);
        $this->assertSame(115, $students[0]->fresh()->current_points);
        $this->assertSame($category->id, $original->category_id);

        $this->put(route('morning-rewards.update', $reward), [
            'student_id' => $students[1]->id,
            'reward_date' => today()->toDateString(),
            'arrival_time' => '05:20',
            'rank' => 2,
        ])->assertRedirect(route('morning-rewards.index'));

        $reward->refresh();
        $this->assertSame(100, $students[0]->fresh()->current_points);
        $this->assertSame(115, $students[1]->fresh()->current_points);
        $this->assertSame(15, $original->fresh()->points);
        $this->assertSame(3, PointTransaction::query()->count());
        $this->assertDatabaseHas('point_transactions', [
            'counter_entry_for' => $original->id,
            'points' => -15,
        ]);
        $newTransaction = $reward->pointTransaction;

        $this->delete(route('morning-rewards.destroy', $reward))
            ->assertRedirect(route('morning-rewards.index'));
        $this->assertDatabaseMissing('morning_rewards', ['id' => $reward->id]);
        $this->assertSame(100, $students[1]->fresh()->current_points);
        $this->assertSame(4, PointTransaction::query()->count());
        $this->assertSame(15, $newTransaction->fresh()->points);
        $this->assertDatabaseHas('point_transactions', [
            'counter_entry_for' => $newTransaction->id,
            'points' => -15,
        ]);
    }

    public function test_morning_reward_is_available_to_operational_accounts_and_enforces_daily_student_rank_and_limit(): void
    {
        [$teacher, $students] = $this->createFixtures(11);
        $category = ViolationCategory::query()->create([
            'name' => 'Reward Pagi',
            'type' => 'reward',
            'points' => 15,
        ]);
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $this->actingAs($operational)->get(route('morning-rewards.index'))->assertOk();

        foreach (range(0, 9) as $index) {
            $this->post(route('morning-rewards.store'), [
                'student_id' => $students[$index]->id,
                'reward_date' => today()->toDateString(),
                'arrival_time' => '04:'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'rank' => $index + 1,
            ])->assertRedirect(route('dashboard'));
        }

        $this->post(route('morning-rewards.store'), [
            'student_id' => $students[10]->id,
            'reward_date' => today()->toDateString(),
            'arrival_time' => '05:00',
            'rank' => 1,
        ])->assertSessionHasErrors(['rank']);
        $this->post(route('morning-rewards.store'), [
            'student_id' => $students[0]->id,
            'reward_date' => today()->toDateString(),
            'arrival_time' => '05:00',
            'rank' => 10,
        ])->assertSessionHasErrors(['student_id']);

        $this->assertSame(10, MorningReward::query()->count());
        $this->assertSame(10, PointTransaction::query()->count());
        $this->assertSame(115, $students[0]->fresh()->current_points);
        $this->assertSame(100, $students[10]->fresh()->current_points);
        $this->assertSame('reward', $category->type);
        $this->actingAs($teacher)->get(route('morning-rewards.index'))->assertOk();
    }

    public function test_additional_points_crud_requires_positive_integer_and_records_corrections(): void
    {
        [$teacher, $students] = $this->createFixtures(2);
        $this->actingAs($teacher)
            ->post(route('additional-points.store'), [
                'student_nis' => $students[0]->nis,
                'points' => 8,
                'awarded_date' => today()->toDateString(),
                'reason' => 'Juara lomba bidang sains',
            ])
            ->assertRedirect(route('additional-points.index'));

        $point = AdditionalPoint::query()->firstOrFail();
        $original = $point->pointTransaction;
        $this->assertSame(8, $original->points);
        $this->assertSame(108, $students[0]->fresh()->current_points);

        $this->put(route('additional-points.update', $point), [
            'student_nis' => $students[1]->nis,
            'points' => 12,
            'awarded_date' => today()->toDateString(),
            'reason' => 'Kontribusi kegiatan sekolah',
        ])->assertRedirect(route('additional-points.index'));

        $point->refresh();
        $this->assertSame(100, $students[0]->fresh()->current_points);
        $this->assertSame(112, $students[1]->fresh()->current_points);
        $this->assertSame(8, $original->fresh()->points);
        $this->assertSame(3, PointTransaction::query()->count());

        $this->post(route('additional-points.store'), [
            'student_nis' => $students[0]->nis,
            'points' => -1,
            'awarded_date' => today()->toDateString(),
            'reason' => 'Input tidak valid',
        ])->assertSessionHasErrors(['points']);

        $current = $point->pointTransaction;
        $this->delete(route('additional-points.destroy', $point))
            ->assertRedirect(route('additional-points.index'));
        $this->assertDatabaseMissing('additional_points', ['id' => $point->id]);
        $this->assertSame(100, $students[1]->fresh()->current_points);
        $this->assertSame(4, PointTransaction::query()->count());
        $this->assertSame(12, $current->fresh()->points);
    }

    public function test_all_point_changes_reconcile_balance_from_active_ledger_and_piket_awards_ten(): void
    {
        [$teacher, $students] = $this->createFixtures(1);
        $category = ViolationCategory::query()->create([
            'name' => 'Piket Gerbang',
            'type' => 'reward',
            'points' => 10,
        ]);
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $schedule = DutySchedule::query()->create([
            'week_start' => today()->startOfWeek()->toDateString(),
            'duty_date' => today()->toDateString(),
            'created_by' => $teacher->id,
        ]);
        $schedule->members()->attach($operational->id, ['student_id' => $students[0]->id]);

        $attendance = app(DutyAttendanceService::class)->record($schedule, $operational, $operational);
        $this->assertSame(10, $attendance->pointTransaction->points);
        $this->assertSame(110, $students[0]->fresh()->current_points);
        $attendance->pointTransaction->update(['is_active' => false]);
        $this->assertSame(100, $students[0]->fresh()->current_points);
        $attendance->pointTransaction->update(['is_active' => true]);
        $this->assertSame(110, $students[0]->fresh()->current_points);

        $this->actingAs($teacher)->post(route('ledgers.store'), [
            'student_id' => $students[0]->id,
            'category_id' => $category->id,
            'transaction_date' => today()->toDateString(),
            'description' => 'Penyesuaian manual yang positif',
        ])->assertRedirect(route('students.show', $students[0]));

        $this->assertSame(120, $students[0]->fresh()->current_points);
        $this->assertSame(
            100 + (int) PointTransaction::query()->where('student_id', $students[0]->id)->where('is_active', true)->sum('points'),
            $students[0]->fresh()->current_points,
        );
    }

    public function test_operational_users_can_manage_additional_points(): void
    {
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $this->actingAs($operational)
            ->get(route('additional-points.index'))
            ->assertOk();
    }

    private function createFixtures(int $studentCount): array
    {
        $teacher = User::factory()->create();
        $studentRoleId = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $students = [];

        foreach (range(1, $studentCount) as $index) {
            $nis = str_pad((string) (12340 + $index), 5, '0', STR_PAD_LEFT);
            $user = User::factory()->create([
                'role_id' => $studentRoleId,
                'username' => $nis,
                'name' => "Siswa Batch 6 {$index}",
            ]);
            $students[] = Student::query()->create([
                'user_id' => $user->id,
                'nis' => $nis,
                'nisn' => '123456'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'gender' => 'Laki-laki',
                'grade' => 'XI',
                'major' => 'RPL',
                'class_name' => 'XI RPL 1',
                'is_active' => true,
                'initial_points' => 100,
                'current_points' => 100,
            ]);
        }

        return [$teacher, $students];
    }
}
