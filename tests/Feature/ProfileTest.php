<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\DutySchedule;
use App\Models\Student;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'testuser',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('testuser', $user->username);
    }

    public function test_username_can_remain_unchanged_when_updating_profile(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => $user->username,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Test User', $user->refresh()->name);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_main_student_teacher_and_operational_accounts_can_open_profile_and_change_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['12345', 'guru0001', 'piket001'] as $username) {
            if (auth()->check()) {
                $this->post(route('logout'))->assertRedirect('/');
            }

            $this->post(route('login'), [
                'username' => $username,
                'password' => $username,
            ])->assertRedirect(route('dashboard'));

            $user = User::query()->where('username', $username)->firstOrFail();

            $this->get(route('profile.edit'))
                ->assertOk()
                ->assertSee(route('password.update'));

            $this->put(route('password.update'), [
                'current_password' => $username,
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])->assertRedirect(route('profile.edit'));

            $this->assertTrue(Hash::check('password-baru-123', $user->fresh()->password));
        }

        foreach (DutySchedule::query()->with('members')->get() as $schedule) {
            $studentIds = $schedule->members->map(fn ($member): ?int => $member->pivot->student_id);
            $this->assertCount($schedule->members->count(), $studentIds->filter());
            $this->assertCount($schedule->members->count(), $studentIds->unique());
            $this->assertSame(
                $schedule->members->count(),
                Student::query()->whereIn('id', $studentIds)->where('is_active', true)->count(),
            );
        }
    }
}
