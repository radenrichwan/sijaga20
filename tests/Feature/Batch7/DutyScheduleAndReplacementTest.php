<?php

declare(strict_types=1);

namespace Tests\Feature\Batch7;

use App\Models\DutyReplacement;
use App\Models\DutySchedule;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\DutyScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DutyScheduleAndReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_preview_is_weekday_only_balanced_and_confirmed_after_editable_review(): void
    {
        $manager = $this->createUser('guru', 'guru0001');
        $members = $this->createOperationalUsers();
        $monday = Carbon::parse('2026-11-09', 'Asia/Jakarta');

        $response = $this->actingAs($manager)->post(route('duty-schedules.preview'), [
            'period' => 'week',
            'start_date' => $monday->toDateString(),
        ]);
        $response->assertOk()
            ->assertViewIs('duty-schedules.preview')
            ->assertSee('Konfirmasi dan simpan jadwal')
            ->assertSee('Petugas (4–6)');
        $this->get(route('duty-schedules.preview-fallback'))
            ->assertRedirect(route('duty-schedules.index'));
        $preview = app(DutyScheduleService::class)->generate($monday, $monday->copy()->addDays(4));
        $this->assertCount(5, $preview);

        $perMember = [];
        foreach ($preview as $date => $userIds) {
            $this->assertSame(4, count($userIds));
            $this->assertSame(count($userIds), count(array_unique($userIds)));
            $this->assertSame(1, Carbon::parse($date)->dayOfWeekIso >= 6 ? 0 : 1);
            foreach ($userIds as $userId) {
                $perMember[$userId] = ($perMember[$userId] ?? 0) + 1;
            }
        }
        $this->assertLessThanOrEqual(1, max($perMember) - min($perMember));

        $this->post(route('duty-schedules.confirm'), [
            'period' => 'week',
            'start_date' => $monday->toDateString(),
            'schedule' => $preview,
        ])->assertRedirect(route('duty-schedules.index', [
            'period' => 'week',
            'start_date' => $monday->toDateString(),
        ]));

        $this->assertSame(5, DutySchedule::query()->count());
        $this->assertSame(20, DB::table('duty_schedule_members')->count());
        $this->assertSame(6, $members->count());
    }

    public function test_schedule_confirmation_rejects_weekends_and_unbalanced_rosters(): void
    {
        $manager = $this->createUser('guru', 'guru0002');
        $members = $this->createOperationalUsers();
        $monday = Carbon::parse('2026-11-09', 'Asia/Jakarta');
        $schedule = [];
        foreach (range(0, 4) as $offset) {
            $schedule[$monday->copy()->addDays($offset)->toDateString()] = $members->take(4)->pluck('id')->all();
        }

        $schedule['2026-11-14'] = $members->take(4)->pluck('id')->all();
        $this->actingAs($manager)->post(route('duty-schedules.confirm'), [
            'period' => 'week',
            'start_date' => $monday->toDateString(),
            'schedule' => $schedule,
        ])->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('duty_schedules', 0);

        unset($schedule['2026-11-14']);
        foreach (array_keys($schedule) as $date) {
            $schedule[$date] = $members->take(4)->pluck('id')->all();
        }
        $this->post(route('duty-schedules.confirm'), [
            'period' => 'week',
            'start_date' => $monday->toDateString(),
            'schedule' => $schedule,
        ])->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('duty_schedules', 0);
    }

    public function test_monthly_preview_contains_only_weekdays_and_can_be_opened(): void
    {
        $manager = $this->createUser('guru', 'guru0004');
        $this->createOperationalUsers();

        $this->actingAs($manager)->post(route('duty-schedules.preview'), [
            'period' => 'month',
            'start_date' => '2026-11-01',
        ])->assertOk()
            ->assertViewIs('duty-schedules.preview')
            ->assertSee('Preview Pembagian Jadwal');

        $preview = app(DutyScheduleService::class)->generate(
            Carbon::parse('2026-11-01', 'Asia/Jakarta')->startOfMonth(),
            Carbon::parse('2026-11-01', 'Asia/Jakarta')->endOfMonth(),
        );
        $this->assertCount(21, $preview);
        foreach (array_keys($preview) as $date) {
            $this->assertLessThan(6, Carbon::parse($date)->dayOfWeekIso);
        }
    }

    public function test_schedule_changes_require_permission_and_active_operational_members(): void
    {
        $members = $this->createOperationalUsers();
        $operational = $members[0];
        $operational->forceFill(['can_manage_schedule' => false])->save();
        $this->actingAs($operational)->get(route('duty-schedules.index'))->assertOk();
        $this->post(route('duty-schedules.preview'), [
            'period' => 'week',
            'start_date' => '2026-11-09',
        ])->assertForbidden();

        $manager = $members[1];
        $this->actingAs($manager)->post(route('duty-schedules.preview'), [
            'period' => 'week',
            'start_date' => '2026-11-09',
        ])->assertOk();
    }

    public function test_replacement_request_is_approved_without_removing_original_schedule_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 05:30:00', 'Asia/Jakarta'));
        $manager = $this->createUser('guru', 'guru0003');
        $members = $this->createOperationalUsers();
        $schedule = $this->createSchedule($members->take(4));
        $original = $members[0];
        $replacement = $members[4];
        $replacementStudent = $this->createStudent($replacement);

        $this->actingAs($original)->post(route('duty-replacements.store'), [
            'duty_schedule_id' => $schedule->id,
            'original_user_id' => $original->id,
            'replacement_user_id' => $replacement->id,
            'replacement_student_id' => $replacementStudent->id,
            'reason' => 'Berhalangan hadir.',
        ])->assertRedirect(route('duty-replacements.index'));
        $request = DutyReplacement::query()->firstOrFail();
        $this->assertSame('pending', $request->status);

        $this->actingAs($manager)->patch(route('duty-replacements.decide', $request), [
            'status' => 'approved',
        ])->assertRedirect(route('duty-replacements.index'));

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertDatabaseHas('duty_schedule_members', [
            'duty_schedule_id' => $schedule->id,
            'user_id' => $original->id,
        ]);
        $this->assertSame(4, $schedule->fresh()->members()->count());
        $this->get(route('duty-replacements.index'))
            ->assertOk()
            ->assertSee('Riwayat dan Persetujuan')
            ->assertSee('Approved');
    }

    public function test_same_day_replacement_request_is_rejected_at_six_am_and_inactive_replacement_is_invalid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 06:00:00', 'Asia/Jakarta'));
        $members = $this->createOperationalUsers();
        $schedule = $this->createSchedule($members->take(4));
        $original = $members[0];
        $inactive = $this->createUser('operasional', 'piket007');
        $inactive->forceFill(['is_active' => false])->save();

        $this->actingAs($original)->post(route('duty-replacements.store'), [
            'duty_schedule_id' => $schedule->id,
            'original_user_id' => $original->id,
            'replacement_user_id' => $members[4]->id,
            'replacement_student_id' => $this->createStudent($members[4])->id,
            'reason' => 'Berhalangan.',
        ])->assertSessionHasErrors('duty_schedule_id');

        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00:00', 'Asia/Jakarta'));
        $futureSchedule = $this->createSchedule($members->take(4), '2026-11-10');
        $this->post(route('duty-replacements.store'), [
            'duty_schedule_id' => $futureSchedule->id,
            'original_user_id' => $original->id,
            'replacement_user_id' => $inactive->id,
            'replacement_student_id' => $this->createStudent($inactive)->id,
            'reason' => 'Berhalangan.',
        ])->assertSessionHasErrors('replacement_user_id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createUser(string $roleName, string $username, bool $canManageSchedule = false): User
    {
        return User::factory()->create([
            'username' => $username,
            'role_id' => Role::query()->where('name', $roleName)->valueOrFail('id'),
            'can_manage_schedule' => $canManageSchedule,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function createOperationalUsers()
    {
        return collect(range(1, 6))->map(
            fn (int $number): User => $this->createUser('operasional', sprintf('piket%03d', $number), $number <= 2),
        );
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function createSchedule($members, string $date = '2026-11-09'): DutySchedule
    {
        $schedule = DutySchedule::query()->create([
            'week_start' => Carbon::parse($date)->startOfWeek()->toDateString(),
            'duty_date' => $date,
            'status' => 'scheduled',
            'created_by' => User::factory()->create()->id,
        ]);
        $schedule->members()->attach($members->pluck('id')->all());

        return $schedule;
    }

    private function createStudent(User $user): Student
    {
        $id = $user->id;

        return Student::query()->create([
            'user_id' => User::factory()->create([
                'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
            ])->id,
            'nis' => str_pad((string) (90000 + $id), 5, '0', STR_PAD_LEFT),
            'nisn' => str_pad((string) (3000000000 + $id), 10, '0', STR_PAD_LEFT),
            'class_name' => 'XI RPL 1',
            'is_active' => true,
            'initial_points' => 100,
            'current_points' => 100,
        ]);
    }
}
