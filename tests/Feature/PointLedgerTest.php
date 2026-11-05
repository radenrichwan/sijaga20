<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PointLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_violation_is_recorded_as_negative_and_deducted_atomically(): void
    {
        $user = User::factory()->create();
        $student = $this->createStudent($user);
        $category = Category::query()->create([
            'name' => 'Terlambat',
            'type' => 'violation',
            'points' => 5,
        ]);

        $response = $this->actingAs($user)->post(route('ledgers.store'), [
            'student_id' => $student->id,
            'category_id' => $category->id,
            'transaction_date' => '2026-11-02',
            'description' => 'Datang melewati batas waktu.',
        ]);

        $response->assertRedirect(route('students.show', $student));
        $this->assertDatabaseHas('point_transactions', [
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $user->id,
            'type' => 'violation',
            'points' => -5,
        ]);
        $this->assertSame(95, $student->refresh()->current_points);
    }

    public function test_reward_is_recorded_as_positive_and_added_to_balance(): void
    {
        $user = User::factory()->create();
        $student = $this->createStudent($user);
        $category = Category::query()->create([
            'name' => 'Prestasi',
            'type' => 'reward',
            'points' => 15,
        ]);

        $this->actingAs($user)->post(route('ledgers.store'), [
            'student_id' => $student->id,
            'category_id' => $category->id,
            'transaction_date' => '2026-11-02',
        ])->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('point_transactions', [
            'student_id' => $student->id,
            'type' => 'reward',
            'points' => 15,
        ]);
        $this->assertSame(115, $student->refresh()->current_points);
    }

    public function test_category_used_in_ledger_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $student = $this->createStudent($user);
        $category = Category::query()->create([
            'name' => 'Pelanggaran',
            'type' => 'violation',
            'points' => 5,
        ]);
        PointLedger::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $user->id,
            'type' => 'violation',
            'points' => -5,
            'transaction_date' => '2026-11-02',
        ]);

        $this->actingAs($user)->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('violation_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('point_transactions', ['category_id' => $category->id]);
    }

    public function test_category_student_and_ledger_forms_render_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $student = $this->createStudent($user);
        Category::query()->create([
            'name' => 'Reward',
            'type' => 'reward',
            'points' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Kategori Poin')
            ->assertSee('data-bs-toggle="modal"', false)
            ->assertSee('data-bs-target="#createCategoryModal"', false);

        $this->get(route('students.index'))
            ->assertOk()
            ->assertSee($student->nisn);

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Total akumulasi poin saat ini');

        $this->get(route('ledgers.create'))
            ->assertOk()
            ->assertSee('Catat transaksi poin');
    }

    public function test_student_point_history_loads_transaction_relations_and_uses_valid_student_route(): void
    {
        $teacher = User::factory()->create();
        $student = $this->createStudent($teacher);
        $category = Category::query()->create([
            'name' => 'Reward Riwayat',
            'type' => 'reward',
            'points' => 10,
        ]);
        PointLedger::query()->create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'recorded_by' => $teacher->id,
            'type' => 'reward',
            'points' => 10,
            'transaction_date' => today()->toDateString(),
        ]);

        $this->actingAs($teacher)
            ->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Reward Riwayat')
            ->assertSee('Riwayat transaksi poin')
            ->assertViewHas('ledgers', function ($ledgers): bool {
                $transaction = $ledgers->first();

                return $transaction !== null
                    && $transaction->relationLoaded('student')
                    && $transaction->relationLoaded('category')
                    && $transaction->relationLoaded('recordedBy');
            });
    }

    public function test_category_can_be_created_and_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('categories.store'), [
            'name' => 'Terlambat',
            'type' => 'violation',
            'points' => 5,
            'description' => 'Datang terlambat.',
        ])->assertRedirect(route('categories.index'));

        $category = Category::query()->firstOrFail();
        $this->put(route('categories.update', $category), [
            'category_id' => $category->id,
            'name' => 'Terlambat lebih dari batas',
            'type' => 'violation',
            'points' => 10,
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('violation_categories', [
            'id' => $category->id,
            'name' => 'Terlambat lebih dari batas',
            'points' => 10,
        ]);
    }

    private function createStudent(User $user): Student
    {
        return Student::query()->create([
            'user_id' => $user->id,
            'nis' => $user->username,
            'nisn' => '0012345678',
            'class_name' => 'X RPL 1',
            'initial_points' => 100,
            'current_points' => 100,
        ]);
    }
}
