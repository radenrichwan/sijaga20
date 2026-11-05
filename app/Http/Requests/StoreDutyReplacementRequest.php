<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DutySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDutyReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user?->isActive() || ! $user->hasRole('guru', 'operasional')) {
            return false;
        }

        return $user->canManageSchedule()
            || DutySchedule::query()
                ->whereKey($this->input('duty_schedule_id'))
                ->whereHas('members', fn ($members) => $members->whereKey($user->id))
                ->exists();
    }

    public function rules(): array
    {
        return [
            'duty_schedule_id' => ['required', 'integer', 'exists:duty_schedules,id'],
            'original_user_id' => [
                'required',
                'integer',
                Rule::exists('duty_schedule_members', 'user_id')
                    ->where('duty_schedule_id', $this->input('duty_schedule_id')),
            ],
            'replacement_user_id' => ['required', 'integer', 'exists:users,id', 'different:original_user_id'],
            'replacement_student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where('is_active', true),
            ],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
