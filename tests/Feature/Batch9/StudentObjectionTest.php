<?php

declare(strict_types=1);

namespace Tests\Feature\Batch9;

use App\Events\StudentObjectionDecided;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentObjection;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentObjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_an_objection_for_an_owned_transaction_with_private_evidence(): void
    {
        Storage::fake('local');
        [$studentUser, $student, $transaction] = $this->createStudentTransaction();

        $this->actingAs($studentUser)->get(route('objections.create'))
            ->assertOk()
            ->assertSee('Catatan pendukung')
            ->assertSee('Bukti pendukung');

        $this->post(route('objections.store'), [
            'point_transaction_id' => $transaction->id,
            'reason' => 'Transaksi ini tidak sesuai dengan kejadian sebenarnya.',
            'supporting_note' => 'Saya tidak berada di sekolah pada waktu tersebut.',
            'evidence' => UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('dashboard'));

        $objection = StudentObjection::query()->firstOrFail();
        $this->assertSame('pending', $objection->status);
        $this->assertSame($student->id, $objection->student_id);
        $this->assertSame('point_transaction', $objection->source_type);
        $this->assertSame($transaction->id, $objection->source_id);
        $this->assertSame('Saya tidak berada di sekolah pada waktu tersebut.', $objection->supporting_note);
        Storage::disk('local')->assertExists($objection->evidence_path);
        $this->assertDatabaseCount('point_transactions', 1);

        $this->actingAs($studentUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Status keberatan saya')
            ->assertSee('Pending');
    }

    public function test_student_cannot_object_to_another_students_transaction_or_submit_duplicate_pending_objections(): void
    {
        [$studentUser, , $transaction] = $this->createStudentTransaction();
        [, , $otherTransaction] = $this->createStudentTransaction('12346');

        $this->actingAs($studentUser)->post(route('objections.store'), [
            'point_transaction_id' => $otherTransaction->id,
            'reason' => 'Saya tidak memiliki transaksi poin ini.',
        ])->assertForbidden();

        $payload = [
            'point_transaction_id' => $transaction->id,
            'reason' => 'Mohon diperiksa kembali transaksi ini.',
        ];
        $this->post(route('objections.store'), $payload)->assertRedirect(route('dashboard'));
        $this->from(route('objections.create'))
            ->post(route('objections.store'), $payload)
            ->assertSessionHasErrors('point_transaction_id');
        $this->assertSame(1, StudentObjection::query()->count());
    }

    public function test_teacher_approval_creates_atomic_append_only_counter_transaction_and_dispatches_hook(): void
    {
        Event::fake([StudentObjectionDecided::class]);
        [$studentUser, $student, $original] = $this->createStudentTransaction();
        $objection = $this->createObjection($student, $original);
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get(route('objections.index'))
            ->assertOk()
            ->assertSee($studentUser->name)
            ->assertSee($objection->reason)
            ->assertSee('Setujui dan koreksi ledger');

        $this->actingAs($teacher)->patch(route('objections.decide', $objection), [
            'status' => 'approved',
            'decision_reason' => 'Catatan sumber telah diverifikasi.',
        ])->assertRedirect(route('objections.index'));

        $objection->refresh();
        $correction = $objection->correctionTransaction;
        $this->assertSame('approved', $objection->status);
        $this->assertSame($teacher->id, $objection->decided_by);
        $this->assertSame(-$original->points, $correction->points);
        $this->assertSame($original->id, $correction->counter_entry_for);
        $this->assertSame('student_objection', $correction->source_type);
        $this->assertSame($objection->id, $correction->source_id);
        $this->assertSame(-5, $original->fresh()->points);
        $this->assertSame(100, $student->fresh()->current_points);
        $this->assertSame(2, PointTransaction::query()->count());
        Event::assertDispatched(StudentObjectionDecided::class, fn (StudentObjectionDecided $event): bool => $event->objection->id === $objection->id);

        $this->actingAs($studentUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee('Catatan sumber telah diverifikasi.')
            ->assertSee('Koreksi poin +5');

        $this->actingAs($teacher)->patch(route('objections.decide', $objection), [
            'status' => 'approved',
            'decision_reason' => 'Keputusan kedua.',
        ])->assertSessionHasErrors('status');
        $this->assertSame(2, PointTransaction::query()->count());
    }

    public function test_operational_user_can_review_objections_without_changing_points_on_rejection(): void
    {
        [, $student, $original] = $this->createStudentTransaction();
        $objection = $this->createObjection($student, $original);
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);

        $this->actingAs($operational)->get(route('objections.index'))->assertOk();
        $this->patch(route('objections.decide', $objection), [
            'status' => 'rejected',
            'decision_reason' => 'Bukti tidak mendukung perubahan.',
        ])->assertRedirect(route('objections.index'));

        $this->assertSame('rejected', $objection->fresh()->status);
        $this->assertSame($operational->id, $objection->fresh()->decided_by);
        $this->assertNull($objection->fresh()->correction_transaction_id);
        $this->assertSame(95, $student->fresh()->current_points);
        $this->assertSame(1, PointTransaction::query()->count());
    }

    public function test_evidence_is_downloadable_by_staff_and_invalid_upload_is_rejected(): void
    {
        Storage::fake('local');
        [$studentUser, $student, $transaction] = $this->createStudentTransaction();
        $payload = [
            'point_transaction_id' => $transaction->id,
            'reason' => 'Catatan ini perlu dibuktikan ulang.',
            'evidence' => UploadedFile::fake()->create('bukti.pdf', 5, 'application/pdf'),
        ];
        $this->actingAs($studentUser)->post(route('objections.store'), $payload)->assertRedirect(route('dashboard'));
        $objection = StudentObjection::query()->firstOrFail();

        $this->actingAs($studentUser)->get(route('objections.evidence', $objection))->assertForbidden();
        $this->actingAs($this->teacher())->get(route('objections.evidence', $objection))->assertOk();

        $anotherTransaction = $this->createTransaction($student);
        $this->actingAs($studentUser)->from(route('objections.create'))->post(route('objections.store'), [
            'point_transaction_id' => $anotherTransaction->id,
            'reason' => 'File dengan format tidak didukung.',
            'evidence' => UploadedFile::fake()->create('bukti.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('evidence');
    }

    /**
     * @return array{User, Student, PointTransaction}
     */
    private function createStudentTransaction(string $nis = '12345'): array
    {
        $studentRole = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $studentUser = User::factory()->create([
            'username' => $nis,
            'role_id' => $studentRole,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nis' => $nis,
            'nisn' => str_pad((string) (3000000000 + $studentUser->id), 10, '0', STR_PAD_LEFT),
            'class_name' => 'XI RPL 1',
            'is_active' => true,
            'initial_points' => 100,
            'current_points' => 100,
        ]);

        return [$studentUser, $student, $this->createTransaction($student)];
    }

    private function createTransaction(Student $student): PointTransaction
    {
        $teacher = $this->teacher();
        $category = ViolationCategory::query()->firstOrCreate(
            ['name' => 'Terlambat', 'type' => 'violation'],
            ['points' => 5],
        );

        return PointTransaction::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'type' => 'violation',
            'points' => -5,
            'description' => 'Catatan keterlambatan.',
            'transaction_date' => today()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function createObjection(Student $student, PointTransaction $transaction): StudentObjection
    {
        return StudentObjection::query()->create([
            'student_id' => $student->id,
            'source_type' => 'point_transaction',
            'source_id' => $transaction->id,
            'reason' => 'Saya mengajukan pemeriksaan atas catatan ini.',
            'status' => 'pending',
        ]);
    }

    private function teacher(): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('name', 'guru')->valueOrFail('id'),
        ]);
    }
}
