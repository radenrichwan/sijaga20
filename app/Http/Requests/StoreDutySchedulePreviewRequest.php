<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDutySchedulePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageSchedule() ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'in:week,month'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
