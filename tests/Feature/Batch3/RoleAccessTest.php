<?php

declare(strict_types=1);

namespace Tests\Feature\Batch3;

use App\Models\Category;
use App\Models\PointLedger;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_redirects_to_login_for_guests(): void
    {
        $this->get('/')
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_database_seeder_creates_the_three_required_roles_and_accounts(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $guru = User::query()->where('username', 'guru0001')->firstOrFail();

        $this->assertSame('guru', $guru->role->name);
        $this->assertSame(3, User::query()->where('role_id', Role::query()->where('name', 'guru')->value('id'))->count());
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(20, Student::query()->count());
        $this->assertSame(27, Category::query()->count());
        $this->assertSame(40, PointLedger::query()->count());
        $this->assertSame(20, User::query()->where('role_id', Role::query()->where('name', 'siswa')->value('id'))->count());
        $this->assertSame(6, User::query()->where('role_id', Role::query()->where('name', 'operasional')->value('id'))->count());

        $seededUsernames = array_merge(
            ['guru0001', 'guru0002', 'guru0003'],
            array_map(static fn (int $number): string => sprintf('piket%03d', $number), range(1, 6)),
            array_map(static fn (int $nis): string => (string) $nis, range(12345, 12364)),
        );

        foreach ($seededUsernames as $username) {
            $this->assertTrue(Hash::check(
                $username,
                User::query()->where('username', $username)->valueOrFail('password'),
            ));
        }

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
        $this->assertSame(20, DB::table('violations')->count());
        $this->assertSame(20, DB::table('additional_points')->count());
        $this->assertSame(40, PointLedger::query()->count());
        $this->assertSame('operasional', User::query()->where('username', 'piket001')->firstOrFail()->role->name);
        $this->assertSame('12345', User::query()->where('username', '12345')->firstOrFail()->username);
        $this->actingAs($guru)->get(route('dashboard'))->assertOk();
    }

    public function test_schedule_gate_requires_the_operational_permission(): void
    {
        $operationalRole = Role::query()->where('name', 'operasional')->valueOrFail('id');
        $operational = User::factory()->create(['role_id' => $operationalRole]);
        $scheduleManager = User::factory()->create([
            'role_id' => $operationalRole,
            'can_manage_schedule' => true,
        ]);
        $teacher = User::factory()->create();

        $this->assertFalse(Gate::forUser($operational)->allows('can_manage_schedule'));
        $this->assertTrue(Gate::forUser($scheduleManager)->allows('can_manage_schedule'));
        $this->assertTrue(Gate::forUser($teacher)->allows('can_manage_schedule'));

        $scheduleManager->forceFill(['is_active' => false])->save();
        $this->assertFalse(Gate::forUser($scheduleManager)->allows('can_manage_schedule'));
    }

    public function test_inactive_and_unrecognized_roles_are_denied_by_role_middleware(): void
    {
        $inactiveTeacher = User::factory()->create(['is_active' => false]);
        $unknownRole = Role::query()->create(['name' => 'administrator']);
        $unrecognizedUser = User::factory()->create(['role_id' => $unknownRole->id]);

        $this->actingAs($inactiveTeacher)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($unrecognizedUser)->get(route('dashboard'))->assertForbidden();
    }

    public function test_operational_account_can_access_staff_modules_except_teacher_only_logs_and_ledger_adjustments(): void
    {
        $operationalRole = Role::query()->where('name', 'operasional')->firstOrFail();
        $operational = User::factory()->create(['role_id' => $operationalRole->id]);

        $this->actingAs($operational)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Akun petugas OSIS/MPK');

        $this->get(route('ledgers.create'))->assertForbidden();
        $this->get(route('categories.index'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('activity-logs.index'))->assertForbidden();
        $this->get(route('students.index'))->assertOk();
        $this->get(route('violations.index'))->assertOk();
        $this->get(route('lateness.index'))->assertOk();
    }

    public function test_student_cannot_access_categories_or_record_point_transactions(): void
    {
        $studentUser = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nis' => $studentUser->username,
            'nisn' => '8012345678',
            'class_name' => 'X RPL 1',
            'initial_points' => 100,
            'current_points' => 100,
        ]);

        $this->actingAs($studentUser)
            ->get(route('categories.index'))
            ->assertForbidden();

        $this->post(route('ledgers.store'), [
            'student_id' => $student->id,
            'category_id' => 1,
            'transaction_date' => today()->toDateString(),
        ])->assertForbidden();

        $this->get(route('ledgers.create'))->assertForbidden();
        $this->get(route('students.index'))->assertForbidden();
    }

    public function test_student_can_view_own_profile_but_not_another_students_profile(): void
    {
        $studentRoleId = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $ownUser = User::factory()->create(['role_id' => $studentRoleId]);
        $ownStudent = $this->createStudent($ownUser, '9012345678');
        $otherStudent = $this->createStudent(User::factory()->create(), '1012345679');

        $this->actingAs($ownUser)
            ->get(route('students.show', $ownStudent))
            ->assertOk()
            ->assertSee($ownStudent->nisn);

        $this->get(route('students.show', $otherStudent))->assertForbidden();
    }

    public function test_student_can_view_only_their_own_point_transactions(): void
    {
        $studentRoleId = Role::query()->where('name', 'siswa')->valueOrFail('id');
        $ownUser = User::factory()->create(['role_id' => $studentRoleId]);
        $otherUser = User::factory()->create(['role_id' => $studentRoleId]);
        $ownStudent = $this->createStudent($ownUser, '2012345678');
        $otherStudent = $this->createStudent($otherUser, '3012345678');
        $teacher = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Terlambat',
            'type' => 'violation',
            'points' => 5,
        ]);
        $ownTransaction = $this->createPointTransaction($ownStudent, $teacher, $category);
        $otherTransaction = $this->createPointTransaction($otherStudent, $teacher, $category);

        $this->assertTrue(Gate::forUser($ownUser)->allows('view', $ownTransaction));
        $this->assertFalse(Gate::forUser($ownUser)->allows('view', $otherTransaction));
        $this->assertFalse(Gate::forUser($ownUser)->allows('create', PointTransaction::class));
    }

    private function createStudent(User $user, string $nisn): Student
    {
        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $user->username,
            'nisn' => $nisn,
            'class_name' => 'X RPL 1',
            'initial_points' => 100,
            'current_points' => 100,
        ]);
    }

    private function createPointTransaction(Student $student, User $teacher, Category $category): PointTransaction
    {
        return PointTransaction::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'type' => 'violation',
            'points' => -5,
            'transaction_date' => today()->toDateString(),
        ]);
    }
}
