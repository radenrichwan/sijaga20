<?php

declare(strict_types=1);

namespace Tests\Feature\Batch2;

use App\Models\ActivityLog;
use App\Models\AdditionalPoint;
use App\Models\DutyAttendance;
use App\Models\DutyReplacement;
use App\Models\DutySchedule;
use App\Models\LatenessRecord;
use App\Models\MorningReward;
use App\Models\Notification;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentObjection;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_models_resolve_relationships_and_cast_schema_values(): void
    {
        $teacher = User::factory()->create();
        $operationalRole = Role::query()->where('name', 'operasional')->valueOrFail('id');
        $operational = User::factory()->create(['role_id' => $operationalRole]);
        $studentRole = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $studentUser = User::factory()->create(['role_id' => $studentRole]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nis' => $studentUser->username,
            'nisn' => '0012345678',
            'class_name' => 'XI RPL 1',
            'initial_points' => '100',
            'current_points' => '95',
            'is_active' => '1',
        ]);
        $category = ViolationCategory::query()->create([
            'name' => 'Terlambat',
            'type' => 'violation',
            'points' => '5',
        ]);
        $violation = Violation::query()->create([
            'student_id' => $student->id,
            'violation_category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'violation_date' => '2026-11-02',
            'description' => 'Terlambat masuk sekolah.',
        ]);
        $transaction = $violation->pointTransactions()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'type' => 'violation',
            'points' => '-5',
            'transaction_date' => '2026-11-02',
        ]);
        $counterEntry = PointTransaction::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'type' => 'reward',
            'points' => 5,
            'transaction_date' => '2026-11-02',
            'counter_entry_for' => $transaction->id,
        ]);

        $lateness = LatenessRecord::query()->create([
            'student_id' => $student->id,
            'recorded_by' => $teacher->id,
            'attendance_date' => '2026-11-02',
            'arrival_time' => '06:35:00',
        ]);
        $morningReward = MorningReward::query()->create([
            'student_id' => $student->id,
            'recorded_by' => $teacher->id,
            'reward_date' => '2026-11-02',
            'arrival_time' => '05:15:00',
            'rank' => '2',
        ]);
        $schedule = DutySchedule::query()->create([
            'week_start' => '2026-11-02',
            'duty_date' => '2026-11-03',
            'created_by' => $teacher->id,
        ]);
        $schedule->members()->attach($operational);
        $replacement = DutyReplacement::query()->create([
            'duty_schedule_id' => $schedule->id,
            'original_user_id' => $operational->id,
            'replacement_user_id' => $teacher->id,
            'requested_by' => $operational->id,
            'reason' => 'Berhalangan hadir.',
        ]);
        $attendance = DutyAttendance::query()->create([
            'duty_schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'recorded_by' => $operational->id,
            'point_transaction_id' => $counterEntry->id,
        ]);
        $additionalPoint = AdditionalPoint::query()->create([
            'student_id' => $student->id,
            'given_by' => $teacher->id,
            'point_transaction_id' => $counterEntry->id,
            'points' => '10',
            'awarded_date' => '2026-11-02',
            'reason' => 'Prestasi akademik.',
        ]);
        $objection = StudentObjection::query()->create([
            'student_id' => $student->id,
            'source_type' => 'violation',
            'source_id' => $violation->id,
            'reason' => 'Catatan perlu ditinjau.',
            'decided_by' => $teacher->id,
            'decided_at' => now(),
        ]);
        $activityLog = ActivityLog::query()->create([
            'user_id' => $teacher->id,
            'role_name' => 'guru',
            'action' => 'violation.created',
            'subject_type' => Violation::class,
            'subject_id' => $violation->id,
            'before_data' => ['status' => 'new'],
            'after_data' => ['status' => 'recorded'],
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);
        $notification = Notification::query()->create([
            'type' => 'point_transaction',
            'notifiable_type' => User::class,
            'notifiable_id' => $studentUser->id,
            'data' => ['message' => 'Poin diperbarui.'],
        ]);

        $this->assertSame($studentUser->id, $student->user->id);
        $this->assertSame($student->id, $violation->student->id);
        $this->assertSame($category->id, $violation->category->id);
        $this->assertSame($teacher->id, $violation->recordedBy->id);
        $this->assertInstanceOf(Violation::class, $transaction->source);
        $this->assertSame($transaction->id, $counterEntry->counterEntryFor->id);
        $this->assertSame($counterEntry->id, $transaction->counterEntries->sole()->id);
        $this->assertSame($teacher->id, $schedule->createdBy->id);
        $this->assertSame($student->id, $lateness->student->id);
        $this->assertSame($teacher->id, $morningReward->recordedBy->id);
        $this->assertSame($operational->id, $schedule->members->sole()->id);
        $this->assertSame($schedule->id, $replacement->schedule->id);
        $this->assertSame($student->id, $attendance->student->id);
        $this->assertSame($teacher->id, $additionalPoint->givenBy->id);
        $this->assertInstanceOf(Violation::class, $objection->source);
        $this->assertSame($teacher->id, $objection->decidedBy->id);
        $this->assertSame($operational->id, $replacement->requestedBy->id);
        $this->assertSame($teacher->id, $activityLog->user->id);
        $this->assertSame($studentUser->id, $notification->notifiable->id);
        $this->assertIsInt($student->current_points);
        $this->assertIsBool($student->is_active);
        $this->assertIsInt($category->points);
        $this->assertIsInt($transaction->points);
        $this->assertIsArray($activityLog->after_data);
        $this->assertSame('2026-11-02', $violation->violation_date->toDateString());
    }
}
