<?php

declare(strict_types=1);

namespace Tests\Feature\Batch5;

use App\Models\LatenessRecord;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationAndLatenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_record_edit_and_delete_violation_with_append_only_corrections(): void
    {
        [$teacher, $student, $category] = $this->createFixtures();

        $this->actingAs($teacher)
            ->post(route('violations.store'), [
                'student_nis' => $student->nis,
                'violation_category_id' => $category->id,
                'violation_date' => today()->toDateString(),
                'description' => 'Tidak memakai atribut lengkap',
            ])
            ->assertRedirect(route('violations.index'));

        $violation = Violation::query()->firstOrFail();
        $original = PointTransaction::query()->findOrFail($violation->point_transaction_id);
        $this->assertSame(-5, $original->points);
        $this->assertSame(95, $student->fresh()->current_points);

        $this->put(route('violations.update', $violation), [
            'violation_category_id' => $category->id,
            'violation_date' => today()->subDay()->toDateString(),
            'description' => 'Catatan diperbarui',
        ])->assertRedirect(route('violations.index'));

        $violation->refresh();
        $this->assertSame(95, $student->fresh()->current_points);
        $this->assertSame(-5, $original->fresh()->points);
        $this->assertSame(3, PointTransaction::query()->count());
        $this->assertDatabaseHas('point_transactions', [
            'counter_entry_for' => $original->id,
            'points' => 5,
            'is_active' => true,
        ]);
        $currentDebit = PointTransaction::query()->findOrFail($violation->point_transaction_id);

        $this->delete(route('violations.destroy', $violation))
            ->assertRedirect(route('violations.index'));

        $this->assertSoftDeleted('violations', ['id' => $violation->id]);
        $this->assertSame(100, $student->fresh()->current_points);
        $this->assertSame(-5, $currentDebit->fresh()->points);
        $this->assertSame(4, PointTransaction::query()->count());
        $this->assertDatabaseHas('point_transactions', [
            'counter_entry_for' => $currentDebit->id,
            'points' => 5,
        ]);
    }

    public function test_operational_user_can_submit_multiple_violation_categories_in_one_request(): void
    {
        [, $student, $category] = $this->createFixtures();
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $secondCategory = ViolationCategory::query()->where('name', 'Terlambat')->firstOrFail();

        $this->actingAs($operational)->post(route('violations.store'), [
            'student_nis' => $student->nis,
            'violation_category_ids' => [$category->id, $category->id, $secondCategory->id],
            'violation_date' => today()->toDateString(),
            'description' => 'Catatan petugas',
        ])->assertRedirect(route('violations.index'))
            ->assertSessionHas('status', '3 pelanggaran dicatat; saldo siswa dikurangi 15 poin.');

        $this->assertDatabaseCount('violations', 3);
        $this->assertSame(85, $student->fresh()->current_points);
        $this->assertDatabaseHas('point_transactions', [
            'student_id' => $student->id,
            'description' => 'Tidak memakai atribut 2X, Terlambat 1X: Catatan petugas',
        ]);
    }

    public function test_lateness_requires_after_0630_and_rejects_duplicate_per_student_and_day(): void
    {
        [$teacher, $student] = $this->createFixtures();
        $this->actingAs($teacher);
        $payload = [
            'student_nis' => $student->nis,
            'attendance_date' => today()->toDateString(),
            'arrival_time' => '06:30',
            'description' => 'Terlambat hadir',
        ];

        $this->post(route('lateness.store'), $payload)
            ->assertSessionHasErrors(['arrival_time']);
        $this->assertSame(100, $student->fresh()->current_points);
        $this->assertDatabaseCount('lateness_records', 0);

        $payload['arrival_time'] = '06:31';
        $this->post(route('lateness.store'), $payload)->assertRedirect(route('lateness.index'));
        $record = LatenessRecord::query()->firstOrFail();
        $this->assertSame(-5, $record->pointTransaction->points);
        $this->assertSame(95, $student->fresh()->current_points);

        $this->post(route('lateness.store'), $payload)
            ->assertSessionHasErrors(['attendance_date']);
        $this->assertSame(1, LatenessRecord::query()->count());
        $this->assertSame(1, PointTransaction::query()->count());
    }

    public function test_lateness_edit_and_delete_append_corrections_and_allow_reentry_after_delete(): void
    {
        [$teacher, $student] = $this->createFixtures();
        $this->actingAs($teacher);
        $payload = [
            'student_nis' => $student->nis,
            'attendance_date' => today()->toDateString(),
            'arrival_time' => '06:45',
            'description' => null,
        ];
        $this->post(route('lateness.store'), $payload)->assertRedirect(route('lateness.index'));
        $record = LatenessRecord::query()->firstOrFail();
        $original = $record->pointTransaction;

        $this->put(route('lateness.update', $record), [
            'attendance_date' => today()->toDateString(),
            'arrival_time' => '07:00',
            'description' => 'Keterangan direvisi',
        ])->assertRedirect(route('lateness.index'));

        $record->refresh();
        $this->assertSame(95, $student->fresh()->current_points);
        $this->assertSame(-5, $original->fresh()->points);
        $this->assertSame(3, PointTransaction::query()->count());
        $this->assertSame(1, LatenessRecord::query()->count());

        $currentDebit = $record->pointTransaction;
        $this->delete(route('lateness.destroy', $record))->assertRedirect(route('lateness.index'));
        $this->assertSoftDeleted('lateness_records', ['id' => $record->id]);
        $this->assertSame(100, $student->fresh()->current_points);
        $this->assertSame(4, PointTransaction::query()->count());

        $payload['arrival_time'] = '06:31';
        $this->post(route('lateness.store'), $payload)->assertRedirect(route('lateness.index'));
        $this->assertSame(1, LatenessRecord::query()->count());
        $this->assertSame(5, PointTransaction::query()->count());
        $this->assertDatabaseHas('point_transactions', [
            'counter_entry_for' => $currentDebit->id,
            'points' => 5,
        ]);
    }

    public function test_teacher_and_operational_users_can_open_module_pages_and_students_cannot(): void
    {
        [$teacher, $student, $category] = $this->createFixtures();
        $this->actingAs($teacher);

        $this->get(route('violations.index', ['month' => today()->format('Y-m'), 'class_name' => $student->class_name]))
            ->assertOk()
            ->assertSee('Riwayat Pelanggaran');
        $this->get(route('violations.create'))->assertOk()->assertSee($student->nis);
        $this->get(route('lateness.index', ['month' => today()->format('Y-m'), 'class_name' => $student->class_name]))
            ->assertOk()
            ->assertSee('Riwayat Keterlambatan');
        $this->get(route('lateness.create'))->assertOk()->assertSee('06.30 WIB');

        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $this->actingAs($operational)
            ->get(route('violations.create'))
            ->assertOk()
            ->assertSee('Tambah Pelanggaran');
        $this->get(route('lateness.index'))->assertOk();

        $studentUser = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        $this->actingAs($studentUser)
            ->get(route('violations.index'))
            ->assertForbidden();
        $this->get(route('lateness.index'))->assertForbidden();
        $this->assertSame('violation', $category->type);
    }

    private function createFixtures(): array
    {
        $teacher = User::factory()->create();
        $studentRole = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $studentUser = User::factory()->create([
            'role_id' => $studentRole,
            'username' => '00123',
            'name' => 'Siswa Uji',
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nis' => '00123',
            'nisn' => '1234567890',
            'gender' => 'Laki-laki',
            'grade' => 'XI',
            'major' => 'RPL',
            'class_name' => 'XI RPL 1',
            'is_active' => true,
            'initial_points' => 100,
            'current_points' => 100,
        ]);
        $category = ViolationCategory::query()->create([
            'name' => 'Tidak memakai atribut',
            'type' => 'violation',
            'points' => 10,
        ]);
        ViolationCategory::query()->create([
            'name' => 'Terlambat',
            'type' => 'violation',
            'points' => 5,
        ]);

        return [$teacher, $student, $category];
    }
}
