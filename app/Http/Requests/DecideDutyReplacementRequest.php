<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideDutyReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageSchedule() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
            'status' => ['required', 'in:approved,rejected'],
        ];
    }
}
