<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDutyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && $this->user()->isOperasional();
    }

    public function rules(): array
    {
        return [
            'duty_schedule_id' => ['required', 'integer', 'exists:duty_schedules,id'],
            'member_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
