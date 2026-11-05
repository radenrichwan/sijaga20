<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdditionalPoint;
use App\Models\DutySchedule;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        DB::transaction(function (): void {
            $roles = Role::query()->pluck('id', 'name');

            $teachers = collect([
                ['username' => 'guru0001', 'name' => 'Siti Rahmawati, S.Pd.', 'teacher_type' => 'bk'],
                ['username' => 'guru0002', 'name' => 'Budi Santoso, S.Pd.', 'teacher_type' => 'bk'],
                ['username' => 'guru0003', 'name' => 'Dewi Anggraini, S.Pd.', 'teacher_type' => null],
            ])->map(function (array $attributes) use ($roles): User {
                return User::query()->updateOrCreate(
                    ['username' => $attributes['username']],
                    [
                        'name' => $attributes['name'],
                        'password' => Hash::make($attributes['username']),
                        'role_id' => $roles['guru'],
                        'teacher_type' => $attributes['teacher_type'],
                        'can_manage_schedule' => false,
                        'is_active' => true,
                    ],
                );
            });

            $operationalUsers = collect(range(1, 6))->map(function (int $number) use ($roles): User {
                $username = sprintf('piket%03d', $number);

                return User::query()->updateOrCreate(
                    ['username' => $username],
                    [
                        'name' => sprintf('Petugas Piket %03d', $number),
                        'password' => Hash::make($username),
                        'role_id' => $roles['operasional'],
                        'teacher_type' => null,
                        'can_manage_schedule' => $number <= 2,
                        'is_active' => true,
                    ],
                );
            });

            $categories = collect([
                ['name' => 'Terlambat', 'type' => 'violation', 'points' => 5, 'description' => 'Terlambat hadir di sekolah.'],
                ['name' => 'Seragam Tidak Rapi', 'type' => 'violation', 'points' => 5, 'description' => 'Seragam tidak sesuai ketentuan.'],
                ['name' => 'Pelanggaran Berat', 'type' => 'violation', 'points' => 10, 'description' => 'Pelanggaran tata tertib berat.'],
                ['name' => 'Juara Lomba', 'type' => 'reward', 'points' => 15, 'description' => 'Prestasi dalam perlombaan.'],
                ['name' => 'Penghargaan Kedisiplinan', 'type' => 'reward', 'points' => 10, 'description' => 'Apresiasi atas kedisiplinan dan kontribusi.'],
            ])->mapWithKeys(function (array $attributes): array {
                $category = ViolationCategory::query()->updateOrCreate(
                    ['name' => $attributes['name']],
                    $attributes,
                );

                return [$attributes['name'] => $category];
            });

            $studentNames = [
                'Aditya Pratama', 'Aisyah Putri', 'Andi Saputra', 'Anisa Maharani',
                'Bagas Ramadhan', 'Citra Lestari', 'Daffa Firmansyah', 'Dinda Permata',
                'Fajar Nugraha', 'Farah Nabila', 'Galih Wicaksono', 'Intan Puspita',
                'Kevin Alexander', 'Maya Safitri', 'Muhammad Rizky', 'Nadia Oktaviani',
                'Rafi Hidayat', 'Salsabila Zahra', 'Vino Kurniawan', 'Zahra Amelia',
            ];
            $classes = ['XII RPL 1', 'XII RPL 2', 'XI TKJ 1', 'XI TKJ 2'];
            $students = [];

            foreach ($studentNames as $index => $name) {
                $nis = (string) (12345 + $index);
                $user = User::query()->updateOrCreate(
                    ['username' => $nis],
                    [
                        'name' => $name,
                        'password' => Hash::make($nis),
                        'role_id' => $roles['siswa'],
                        'teacher_type' => null,
                        'can_manage_schedule' => false,
                        'is_active' => true,
                    ],
                );
                $students[] = Student::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nis' => $nis,
                        'nisn' => (string) (3000000000 + $index),
                        'gender' => $index % 2 === 0 ? 'Laki-laki' : 'Perempuan',
                        'grade' => str_starts_with($classes[$index % count($classes)], 'XII') ? 'XII' : 'XI',
                        'major' => str_contains($classes[$index % count($classes)], 'RPL') ? 'Rekayasa Perangkat Lunak' : 'Teknik Komputer dan Jaringan',
                        'class_name' => $classes[$index % count($classes)],
                        'is_active' => true,
                        'initial_points' => 100,
                        'current_points' => 100,
                    ],
                );
            }

            foreach ($students as $index => $student) {
                $teacher = $teachers[$index % $teachers->count()];
                $operational = $operationalUsers[$index % $operationalUsers->count()];
                $transactionDate = today()->subDays($index % 14)->toDateString();
                $category = $categories['Terlambat'];

                $violation = Violation::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'description' => 'Tercatat terlambat pada data demonstrasi.',
                    ],
                    [
                        'violation_category_id' => $category->id,
                        'recorded_by' => $teacher->id,
                        'violation_date' => $transactionDate,
                    ],
                );
                $violationLedger = PointTransaction::query()->updateOrCreate(
                    [
                        'source_type' => 'violation',
                        'source_id' => $violation->id,
                    ],
                    [
                        'student_id' => $student->id,
                        'category_id' => $category->id,
                        'recorded_by' => $teacher->id,
                        'type' => 'violation',
                        'points' => -abs($category->points),
                        'description' => 'Terlambat hadir di sekolah (data demonstrasi).',
                        'transaction_date' => $transactionDate,
                        'is_active' => true,
                    ],
                );
                $violation->update(['point_transaction_id' => $violationLedger->id]);

                $rewardCategory = $categories['Juara Lomba'];
                $additionalPoint = AdditionalPoint::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'reason' => 'Apresiasi prestasi siswa (data demonstrasi).',
                    ],
                    [
                        'given_by' => $teacher->id,
                        'points' => $rewardCategory->points,
                        'awarded_date' => $transactionDate,
                    ],
                );
                $rewardLedger = PointTransaction::query()->updateOrCreate(
                    [
                        'source_type' => 'additional_point',
                        'source_id' => $additionalPoint->id,
                    ],
                    [
                        'student_id' => $student->id,
                        'category_id' => $rewardCategory->id,
                        'recorded_by' => $operational->id,
                        'type' => 'reward',
                        'points' => abs($rewardCategory->points),
                        'description' => 'Juara Lomba: apresiasi prestasi siswa.',
                        'transaction_date' => $transactionDate,
                        'is_active' => true,
                    ],
                );
                $additionalPoint->update(['point_transaction_id' => $rewardLedger->id]);

                $student->forceFill([
                    'current_points' => $student->initial_points + $student->pointTransactions()->sum('points'),
                ])->save();
            }

            $weekStart = today()->startOfWeek();
            foreach (range(0, 4) as $dayOffset) {
                $dutyDate = $weekStart->copy()->addDays($dayOffset);
                $schedule = DutySchedule::query()
                    ->whereDate('duty_date', $dutyDate->toDateString())
                    ->first();
                $schedule ??= new DutySchedule;
                $schedule->fill([
                    'week_start' => $weekStart->toDateString(),
                    'duty_date' => $dutyDate->toDateString(),
                    'status' => 'scheduled',
                    'created_by' => $teachers[0]->id,
                ])->save();
                $members = [];

                foreach (range(0, 3) as $memberOffset) {
                    $memberIndex = ($dayOffset + $memberOffset) % $operationalUsers->count();
                    $members[$operationalUsers[$memberIndex]->id] = [
                        'student_id' => $students[$memberIndex]->id,
                    ];
                }

                $schedule->members()->sync($members);
            }
        });
    }
}
