<?php

declare(strict_types=1);

namespace Tests\Feature\Batch3;

use App\Models\Category;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_shows_only_role_appropriate_menu_items(): void
    {
        $teacher = User::factory()->create();
        $teacherResponse = $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Data Siswa')
            ->assertSee('Pelanggaran')
            ->assertSee('Jadwal Piket')
            ->assertSee('Activity Log')
            ->assertSee('Laporan')
            ->assertSee('data-bs-target="#logoutConfirmationModal"', false)
            ->assertSee('Konfirmasi Keluar')
            ->assertSee('class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>', false)
            ->assertSee('class="btn btn-danger" type="submit">Ya, Keluar</button>', false);
        $teacherHtml = $teacherResponse->getContent();
        $modalPosition = strpos($teacherHtml, 'id="logoutConfirmationModal"');
        $lastNavigationClose = strrpos($teacherHtml, '</nav>');
        $this->assertNotFalse($modalPosition);
        $this->assertNotFalse($lastNavigationClose);
        $this->assertLessThan($modalPosition, $lastNavigationClose);

        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $this->actingAs($operational)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Operasional Piket')
            ->assertSee('Absensi Piket')
            ->assertSee('Penghargaan Pagi')
            ->assertSee('Data Siswa')
            ->assertSee('Pelanggaran')
            ->assertSee('Terlambat')
            ->assertSee('Kategori Poin')
            ->assertSee('Laporan')
            ->assertDontSee('Activity Log');

        $student = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        $studentProfile = Student::query()->create([
            'user_id' => $student->id,
            'nis' => $student->username,
            'nisn' => '8812345678',
            'class_name' => 'X RPL 1',
        ]);
        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Pribadi')
            ->assertSee('Riwayat Poin')
            ->assertSee('href="'.route('students.show', $studentProfile).'"', false)
            ->assertSee('Pengajuan Keberatan')
            ->assertDontSee('Data Siswa')
            ->assertDontSee('Laporan');

        $this->get(route('students.show', $studentProfile))
            ->assertOk()
            ->assertSee('Riwayat transaksi poin');

        $this->get(route('categories.catalog'))->assertOk()->assertSee('Kategori Poin');
    }

    public function test_point_category_pages_show_signed_points_for_each_role(): void
    {
        Category::query()->create(['name' => 'Reward Siswa', 'type' => 'reward', 'points' => 15]);
        Category::query()->create(['name' => 'Pelanggaran Siswa', 'type' => 'violation', 'points' => 5]);
        $teacher = User::factory()->create();
        $operational = User::factory()->create([
            'role_id' => Role::query()->where('name', 'operasional')->valueOrFail('id'),
        ]);
        $student = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        Student::query()->create([
            'user_id' => $student->id,
            'nis' => $student->username,
            'nisn' => '8812345679',
            'class_name' => 'X RPL 1',
        ]);

        foreach ([$teacher, $operational] as $staff) {
            $this->actingAs($staff)->get(route('categories.index'))
                ->assertOk()
                ->assertSee('Kategori Poin')
                ->assertSee('+15')
                ->assertSee('-5');
        }

        $this->actingAs($student)->get(route('categories.catalog'))
            ->assertOk()
            ->assertSee('Kategori Poin')
            ->assertSee('+15')
            ->assertSee('-5');
    }

    public function test_student_can_create_an_objection_only_for_their_own_transaction(): void
    {
        $studentUser = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        $otherStudentUser = User::factory()->create([
            'role_id' => Role::query()->where('name', 'siswa')->valueOrFail('id'),
        ]);
        $student = $this->createStudent($studentUser, '9912345678');
        $otherStudent = $this->createStudent($otherStudentUser, '9912345679');
        $teacher = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Pelanggaran',
            'type' => 'violation',
            'points' => 5,
        ]);
        $ownTransaction = $this->createTransaction($student, $teacher, $category);
        $otherTransaction = $this->createTransaction($otherStudent, $teacher, $category);

        $this->actingAs($studentUser)->post(route('objections.store'), [
            'point_transaction_id' => $ownTransaction->id,
            'reason' => 'Catatan ini perlu diperiksa kembali.',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('student_objections', [
            'student_id' => $student->id,
            'source_type' => 'point_transaction',
            'source_id' => $ownTransaction->id,
            'status' => 'pending',
        ]);

        $this->post(route('objections.store'), [
            'point_transaction_id' => $otherTransaction->id,
            'reason' => 'Saya mencoba mengajukan transaksi siswa lain.',
        ])->assertForbidden();
    }

    public function test_notifications_are_scoped_to_the_authenticated_user_and_can_be_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownNotificationId = (string) Str::uuid();
        $otherNotificationId = (string) Str::uuid();

        foreach ([[$ownNotificationId, $user], [$otherNotificationId, $otherUser]] as [$id, $owner]) {
            DB::table('notifications')->insert([
                'id' => $id,
                'type' => 'App\\Notifications\\TestNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $owner->id,
                'data' => json_encode(['message' => 'Informasi akun.'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Informasi akun.')
            ->assertSee('1 belum dibaca');

        $this->patch(route('notifications.read', $ownNotificationId))
            ->assertRedirect(route('notifications.index'));
        $this->assertNotNull(DB::table('notifications')->where('id', $ownNotificationId)->value('read_at'));

        $this->patch(route('notifications.read', $otherNotificationId))->assertNotFound();
    }

    private function createStudent(User $user, string $nisn): Student
    {
        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $user->username,
            'nisn' => $nisn,
            'class_name' => 'X RPL 1',
        ]);
    }

    private function createTransaction(Student $student, User $teacher, Category $category): PointTransaction
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
