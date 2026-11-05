<?php

declare(strict_types=1);

namespace Tests\Feature\Batch4;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use App\Models\PointTransaction;
use App\Services\StudentSpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_update_and_toggle_a_student_account(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->post(route('students.store'), $this->studentData())
            ->assertRedirect(route('students.index'));

        $student = Student::query()->where('nis', '00123')->firstOrFail();
        $studentUser = $student->user;
        $this->assertSame('00123', $studentUser->username);
        $this->assertTrue(Hash::check('00123', $studentUser->password));
        $this->assertSame('XI RPL 1', $student->class_name);

        $this->put(route('students.update', $student), $this->studentData([
            'nisn' => '1234567891',
            'name' => 'Nama Diperbarui',
            'is_active' => '1',
        ]))->assertRedirect(route('students.index'));

        $this->assertSame('Nama Diperbarui', $student->fresh()->user->name);
        $this->assertSame('1234567891', $student->fresh()->nisn);

        $this->patch(route('students.status', $student), ['is_active' => '0'])
            ->assertRedirect(route('students.index'));
        $this->assertFalse($student->fresh()->is_active);
        $this->assertFalse($studentUser->fresh()->is_active);

        $this->patch(route('students.status', $student), ['is_active' => '1'])
            ->assertRedirect(route('students.index'));
        $this->assertTrue($student->fresh()->is_active);
        $this->assertTrue($studentUser->fresh()->is_active);
    }

    public function test_teacher_can_filter_search_and_export_student_data(): void
    {
        $teacher = User::factory()->create();
        $first = $this->createStudent('00124', 'Siswa Uji Satu', 'XI', 'RPL', 'XI RPL 1', true);
        $this->createStudent('00125', 'Siswa Uji Dua', 'XII', 'TKJ', 'XII TKJ 1', false);

        $this->actingAs($teacher)
            ->get(route('students.index', ['search' => '00124', 'grade' => 'XI', 'major' => 'RPL', 'status' => 'active']))
            ->assertOk()
            ->assertSee('Siswa Uji Satu')
            ->assertDontSee('Siswa Uji Dua');

        $csvResponse = $this->get(route('students.export', ['search' => '00124']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('00124', $csvResponse->streamedContent());

        $this->get(route('students.export.excel'))
            ->assertDownload('data-siswa.xlsx');

        $this->assertSame('00124', $first->nis);
    }

    public function test_csv_import_keeps_leading_zero_nis_and_reports_invalid_rows(): void
    {
        $teacher = User::factory()->create();
        $this->createStudent('00126', 'Siswa Sudah Ada', 'X', 'RPL', 'X RPL 1');
        $csv = implode("\n", [
            'nis,nisn,name,gender,grade,major,class_name,is_active',
            '00127,1234567892,Siswa Impor,Laki-laki,XI,RPL,XI RPL 2,aktif',
            '00127,1234567893,Siswa Duplikat,Perempuan,XI,RPL,XI RPL 2,aktif',
            '12,1234567894,NIS Tidak Valid,Laki-laki,X,TKJ,X TKJ 1,aktif',
            '00126,1234567895,NIS Sudah Ada,Perempuan,X,RPL,X RPL 1,aktif',
        ]);

        $this->actingAs($teacher)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv)])
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('status', '1 siswa berhasil diimpor.')
            ->assertSessionHas('import_errors', fn (array $errors): bool => count($errors) === 3);

        $this->assertDatabaseHas('students', ['nis' => '00127', 'nisn' => '1234567892']);
        $this->assertDatabaseMissing('students', ['nis' => '00127', 'nisn' => '1234567893']);
        $this->assertDatabaseHas('users', ['username' => '00127', 'name' => 'Siswa Impor']);
    }

    public function test_teacher_can_download_import_template_and_student_forms_list_every_student(): void
    {
        $teacher = User::factory()->create(['username' => 'guru0001']);
        $this->createStudent('12345', 'Aditya Pratama', 'XI', 'RPL', 'XI RPL 1');
        $this->createStudent('54321', 'Siswa Nonaktif', 'XII', 'TKJ', 'XII TKJ 1', false);

        $template = $this->actingAs($teacher)
            ->get(route('students.import.template'))
            ->assertDownload('template-import-siswa.csv')
            ->streamedContent();
        $this->assertSame("nis,nisn,name,gender,grade,major,class_name,is_active\r\n", $template);

        foreach ([
            route('dashboard'),
            route('violations.create'),
            route('lateness.create'),
            route('additional-points.create'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Aditya Pratama')
                ->assertSee('12345')
                ->assertSee('select2-student')
                ->assertSee('Siswa Nonaktif')
                ->assertSee('Nonaktif');
        }
    }

    public function test_excel_export_and_reader_preserve_nis_as_text(): void
    {
        $teacher = User::factory()->create();
        $this->createStudent('00124', 'Siswa Excel', 'XI', 'RPL', 'XI RPL 1');
        $excel = $this->actingAs($teacher)
            ->get(route('students.export.excel'))
            ->assertDownload('data-siswa.xlsx')
            ->streamedContent();

        $rows = app(StudentSpreadsheetReader::class)->read(
            UploadedFile::fake()->createWithContent('data-siswa.xlsx', $excel),
        );

        $this->assertSame('nis', $rows[0]['values'][0]);
        $this->assertSame('00124', $rows[1]['values'][0]);
        $this->assertSame('Siswa Excel', $rows[1]['values'][2]);
    }

    public function test_student_is_read_only_and_can_only_view_their_own_profile(): void
    {
        $studentRoleId = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $studentUser = User::factory()->create([
            'role_id' => $studentRoleId,
            'name' => 'Milik Saya',
        ]);
        $ownStudent = $this->createStudent($studentUser->username, 'Milik Saya', 'X', 'RPL', 'X RPL 1', true, $studentUser);
        $otherStudent = $this->createStudent('00999', 'Siswa Lain', 'X', 'RPL', 'X RPL 1');

        $this->actingAs($studentUser)
            ->get(route('students.show', $ownStudent))
            ->assertOk()
            ->assertSee('Milik Saya');

        $this->get(route('students.show', $otherStudent))->assertForbidden();
        $this->get(route('students.index'))->assertForbidden();
        $this->post(route('students.store'), $this->studentData())->assertForbidden();
        $this->put(route('students.update', $ownStudent), $this->studentData())->assertForbidden();
        $this->patch(route('students.status', $ownStudent), ['is_active' => '0'])->assertForbidden();
        $this->patch(route('profile.update'), ['name' => 'Diubah', 'username' => $studentUser->username])->assertForbidden();
        $this->get(route('profile.edit'))->assertRedirect(route('students.show', $ownStudent));
    }

    public function test_student_list_is_paginated(): void
    {
        $teacher = User::factory()->create();
        foreach (range(10000, 10015) as $nis) {
            $this->createStudent((string) $nis, "Siswa {$nis}", 'X', 'RPL', 'X RPL 1');
        }

        $this->actingAs($teacher)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Menampilkan 1–15 dari 16 siswa')
            ->assertSee('page=2');
    }

    public function test_student_can_download_only_their_own_point_transcript_pdf(): void
    {
        $studentRole = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $user = User::factory()->create(['role_id' => $studentRole, 'username' => '00999']);
        $student = Student::query()->create([
            'user_id' => $user->id,
            'nis' => '00999',
            'nisn' => '1234567899',
            'gender' => 'Laki-laki',
            'grade' => 'XI',
            'major' => 'RPL',
            'class_name' => 'XI RPL 1',
            'initial_points' => 100,
            'current_points' => 100,
        ]);
        $teacher = User::factory()->create();
        $category = ViolationCategory::query()->create([
            'name' => 'Tidak Pakai Dasi',
            'type' => 'violation',
            'points' => 5,
        ]);
        PointTransaction::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'type' => 'violation',
            'points' => -5,
            'description' => 'Tidak Pakai Dasi 1X',
            'transaction_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('students.transcript', $student))
            ->assertDownload('transkrip-poin-00999.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $otherStudent = $this->createStudent('00998', 'Siswa Lain', 'XI', 'RPL', 'XI RPL 1');
        $this->get(route('students.transcript', $otherStudent))->assertForbidden();
    }

    private function studentData(array $overrides = []): array
    {
        return array_merge([
            'nis' => '00123',
            'nisn' => '1234567890',
            'name' => 'Siswa Contoh',
            'gender' => 'Laki-laki',
            'grade' => 'XI',
            'major' => 'Rekayasa Perangkat Lunak',
            'class_name' => 'XI RPL 1',
            'is_active' => '1',
        ], $overrides);
    }

    private function createStudent(
        string $nis,
        string $name,
        string $grade,
        string $major,
        string $className,
        bool $active = true,
        ?User $user = null,
    ): Student {
        $user ??= User::factory()->create([
            'name' => $name,
            'username' => $nis,
            'is_active' => $active,
        ]);

        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $nis,
            'nisn' => '123456'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'gender' => 'Laki-laki',
            'grade' => $grade,
            'major' => $major,
            'class_name' => $className,
            'is_active' => $active,
        ]);
    }
}
