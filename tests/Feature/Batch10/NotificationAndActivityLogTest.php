<?php

declare(strict_types=1);

namespace Tests\Feature\Batch10;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Notifications\InternalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class NotificationAndActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_summaries_are_compact_and_localized_by_category(): void
    {
        $category = ActivityLog::make([
            'category' => 'category',
            'action' => 'create',
            'new_values' => ['name' => 'Juara Nasional', 'type' => 'reward', 'points' => 50],
        ]);
        $this->assertSame(
            'Kategori Baru: Juara Nasional (+50 Poin, Reward)',
            $category->new_values_summary,
        );

        $replacement = ActivityLog::make([
            'category' => 'duty_replacement',
            'new_values' => [
                'duty_schedule_id' => 4,
                'original_user_id' => 9,
                'replacement_user_id' => 6,
                'status' => 'pending',
            ],
        ]);
        $this->assertSame(
            'Penggantian Jadwal Piket #4: User ID 9 digantikan oleh User ID 6 (Pending)',
            $replacement->new_values_summary,
        );

        $lateness = ActivityLog::make([
            'category' => 'lateness_record',
            'new_values' => [
                'student_id' => 'Aditya Pratama (12345)',
                'attendance_date' => '2026-11-04',
                'points' => -5,
            ],
        ]);
        $this->assertSame(
            'Aditya Pratama (12345), Terlambat, 04/11/2026, -5',
            $lateness->new_values_summary,
        );

        $objection = ActivityLog::make([
            'category' => 'student_objection',
            'old_values' => ['status' => 'pending', 'decision_reason' => null, 'decided_by' => null],
            'new_values' => ['status' => 'approved', 'decision_reason' => 'Bukti diterima'],
        ]);
        $this->assertSame('Status: Menunggu Persetujuan (Pending)', $objection->old_values_summary);
        $this->assertStringContainsString('Disetujui', $objection->new_values_summary);
        $this->assertStringContainsString('(Alasan: Bukti diterima)', $objection->new_values_summary);

        $replacement = ActivityLog::make([
            'category' => 'duty_replacement',
            'old_values' => ['status' => 'pending', 'decision_reason' => null],
            'new_values' => ['status' => 'rejected', 'decision_reason' => 'Jadwal bentrok'],
        ]);
        $this->assertSame('Status penggantian: Menunggu Persetujuan (Pending)', $replacement->old_values_summary);
        $this->assertSame('Penggantian Anggota Piket: Ditolak (Alasan: Jadwal bentrok)', $replacement->new_values_summary);
    }

    public function test_notification_center_shows_unread_count_and_marks_one_or_all_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new InternalNotification(
            'violation.created',
            'Catatan pelanggaran baru',
            'Saldo poin Anda berkurang 5.',
            route('dashboard'),
            ['points' => -5],
        ));
        $user->notify(new InternalNotification(
            'lateness.created',
            'Catatan keterlambatan baru',
            'Saldo poin Anda berkurang 5.',
        ));
        $first = $user->notifications()->oldest()->firstOrFail();

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('2 belum dibaca')
            ->assertSee('Catatan pelanggaran baru')
            ->assertSee('Buka')
            ->assertSee('Tandai semua dibaca');

        $this->patch(route('notifications.read', $first->id))
            ->assertRedirect(route('notifications.index'));
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        $this->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_violation_create_update_delete_are_audited_and_notify_the_affected_student(): void
    {
        $teacher = $this->user('guru', 'guru0001');
        $studentUser = $this->user('siswa', '12345');
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nis' => '12345',
            'nisn' => '3000000001',
            'class_name' => 'XI RPL 1',
            'is_active' => true,
            'initial_points' => 100,
            'current_points' => 100,
        ]);
        $category = ViolationCategory::query()->create([
            'name' => 'Seragam',
            'type' => 'violation',
            'points' => 5,
        ]);
        $teacherCategory = Category::query()->create([
            'name' => 'Pelanggaran',
            'type' => 'violation',
            'points' => 5,
        ]);

        $this->actingAs($teacher)->post(route('violations.store'), [
            'student_nis' => $student->nis,
            'violation_category_id' => $category->id,
            'violation_date' => today()->toDateString(),
            'description' => 'Atribut tidak lengkap',
        ])->assertRedirect(route('violations.index'));

        $violation = Violation::query()->firstOrFail();
        $notification = $studentUser->notifications()->firstOrFail();
        $this->assertSame('violation.created', $notification->data['event']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $teacher->id,
            'role_name' => 'guru',
            'action' => 'create',
            'category' => 'violation',
            'subject_id' => $violation->id,
        ]);
        $createdLog = ActivityLog::query()->where('category', 'violation')->firstOrFail();
        $this->assertSame(-5, $createdLog->new_values['points']);
        $this->assertSame($studentUser->name.' ('.$student->nis.')', $createdLog->new_values['student_id']);
        $this->actingAs($teacher)->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee($studentUser->name.' ('.$student->nis.'), Seragam, '.today()->format('d/m/Y').', -5')
            ->assertDontSee('"student_id"');

        $this->put(route('violations.update', $violation), [
            'violation_category_id' => $category->id,
            'violation_date' => today()->toDateString(),
            'description' => 'Atribut diperiksa kembali',
        ])->assertRedirect(route('violations.index'));
        $updatedLog = ActivityLog::query()->where('action', 'update')->where('category', 'violation')->firstOrFail();
        $this->assertSame('Atribut tidak lengkap', $updatedLog->old_values['description']);
        $this->assertSame('Atribut diperiksa kembali', $updatedLog->new_values['description']);

        $this->delete(route('violations.destroy', $violation))->assertRedirect(route('violations.index'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'delete',
            'category' => 'violation',
            'subject_id' => $violation->id,
        ]);
        $this->assertSame('Pelanggaran', $teacherCategory->name);
    }

    public function test_activity_log_is_teacher_only_searchable_immutable_and_has_no_mutation_routes(): void
    {
        $teacher = $this->user('guru', 'guru0002');
        $operational = $this->user('operasional', 'piket001');
        $student = $this->user('siswa', '12346');
        $entry = ActivityLog::query()->create([
            'user_id' => $teacher->id,
            'role_name' => 'guru',
            'action' => 'approve',
            'category' => 'student_objection',
            'subject_type' => 'student_objection',
            'subject_id' => 42,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved'],
            'occurred_at' => now('Asia/Jakarta'),
        ]);

        $this->actingAs($teacher)->get(route('activity-logs.index', [
            'search' => 'student_objection',
            'action' => 'approve',
            'user_id' => $teacher->id,
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('Activity Log')
            ->assertSee('student_objection')
        ->assertSee('Menunggu Persetujuan')
            ->assertSee('Disetujui');

        $this->actingAs($student)->get(route('activity-logs.index'))->assertForbidden();
        $this->actingAs($operational)->get(route('activity-logs.index'))->assertForbidden();

        try {
            $entry->update(['action' => 'delete']);
            $this->fail('Updating an activity log should be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Activity logs are immutable.', $exception->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Deleting an activity log should be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Activity logs are immutable.', $exception->getMessage());
        }

        $this->assertFalse(
            collect(Route::getRoutes()->getRoutes())
                ->contains(fn ($route): bool => str_contains($route->uri(), 'activity-logs')
                    && in_array(strtoupper($route->methods()[0]), ['PUT', 'PATCH', 'DELETE'], true)),
        );
    }

    private function user(string $role, string $username): User
    {
        return User::factory()->create([
            'username' => $username,
            'role_id' => Role::query()->where('name', $role)->valueOrFail('id'),
        ]);
    }
}
