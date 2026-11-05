<?php

declare(strict_types=1);

namespace Tests\Feature\Batch3;

use App\Models\Category;
use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\PointLedger;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_filter_report_and_export_csv_excel_and_print_view(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $studentA = $this->createStudent('6012345678', 'X RPL 1');
        $studentB = $this->createStudent('7012345678', 'XI TKJ 1');
        $violation = Category::query()->create(['name' => 'Terlambat', 'type' => 'violation', 'points' => 5]);
        $reward = Category::query()->create(['name' => 'Prestasi', 'type' => 'reward', 'points' => 10]);
        $this->createLedger($studentA, $teacher, $violation, 'violation', -5, today()->toDateString());
        $this->createLedger($studentB, $teacher, $reward, 'reward', 10, today()->toDateString());

        $filters = [
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'class_name' => 'X RPL 1',
            'type' => 'violation',
        ];

        $this->actingAs($teacher)
            ->get(route('reports.index', $filters))
            ->assertOk()
            ->assertSee('Terlambat')
            ->assertSee('6012345678')
            ->assertDontSee('7012345678');

        $csvResponse = $this->get(route('reports.export.csv', $filters));
        $csvResponse->assertOk()->assertDownload();
        $this->assertStringContainsString('6012345678', $csvResponse->streamedContent());
        $this->assertStringContainsString('1 kali', $csvResponse->streamedContent());
        $this->assertStringNotContainsString('7012345678', $csvResponse->streamedContent());

        $this->get(route('reports.export.excel', $filters))
            ->assertOk()
            ->assertDownload();

        $pdfResponse = $this->get(route('reports.print', $filters));
        $pdfResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    public function test_report_summarizes_category_frequency_and_filters_by_class_major_month_year_semester_and_category(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $student = $this->createStudent('8012345678', 'XI AKL 1');
        $student->update(['major' => 'Akuntansi']);
        $category = Category::query()->create(['name' => 'Baju tidak rapi', 'type' => 'violation', 'points' => 5]);
        $date = now()->startOfMonth()->toDateString();

        foreach (range(1, 5) as $index) {
            $this->createLedger($student, $teacher, $category, 'violation', -5, $date);
        }
        $schedule = DutySchedule::query()->create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'duty_date' => $date,
            'status' => 'scheduled',
            'created_by' => $teacher->id,
        ]);
        DutyAttendance::query()->create([
            'duty_schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'member_user_id' => $student->user_id,
            'recorded_by' => $teacher->id,
        ]);

        $filters = [
            'class_name' => 'XI AKL 1',
            'major' => 'Akuntansi',
            'month' => now()->month,
            'year' => now()->year,
            'semester' => now()->month >= 7 ? '1' : '2',
            'category_id' => $category->id,
        ];

        $this->actingAs($teacher)
            ->get(route('reports.index', $filters))
            ->assertOk()
            ->assertSee('5 kali (-25 poin')
            ->assertSee('Kelas XI AKL 1')
            ->assertSee('1 kehadiran');

        $csvResponse = $this->get(route('reports.export.csv', $filters));
        $csvResponse->assertOk()->assertDownload();
        $this->assertStringContainsString('5 kali', $csvResponse->streamedContent());
        $this->assertStringContainsString('-25 poin', $csvResponse->streamedContent());
    }

    public function test_report_search_is_trimmed_and_grouped_with_other_filters(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $student = $this->createStudent('8112345678', 'X RPL 1');
        $student->user()->update(['name' => 'Aditya Pratama']);
        $other = $this->createStudent('9912345678', 'XI TKJ 1');
        $other->user()->update(['name' => 'Siswa Lain']);

        $this->actingAs($teacher)
            ->get(route('reports.index', [
                'search' => '  Aditya Pratama  ',
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
                'class_name' => 'X RPL 1',
            ]))
            ->assertOk()
            ->assertSee('Aditya Pratama')
            ->assertDontSee('Siswa Lain');

        $this->get(route('reports.index', [
            'search' => '  9912345678  ',
            'class_name' => 'X RPL 1',
        ]))->assertOk()
            ->assertDontSee('Aditya Pratama')
            ->assertDontSee('Siswa Lain');

        $this->get(route('reports.index', [
            'name' => '  Aditya  ',
            'nisn' => '  811234  ',
        ]))->assertOk()
            ->assertSee('Aditya Pratama')
            ->assertDontSee('Siswa Lain');
    }

    public function test_admin_can_access_reports_and_invalid_date_range_is_rejected(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
        $this->get(route('reports.index', [
            'start_date' => today()->toDateString(),
            'end_date' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('end_date');
    }

    private function createUserWithRole(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('name', $roleName === 'teacher' ? 'guru' : $roleName)->valueOrFail('id'),
        ]);
    }

    private function createStudent(string $nisn, string $className): Student
    {
        $user = User::factory()->create();

        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $user->username,
            'nisn' => $nisn,
            'class_name' => $className,
            'initial_points' => 100,
            'current_points' => 100,
        ]);
    }

    private function createLedger(Student $student, User $recorder, Category $category, string $type, int $points, string $date): void
    {
        PointLedger::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $recorder->id,
            'type' => $type,
            'points' => $points,
            'transaction_date' => $date,
        ]);
    }
}
