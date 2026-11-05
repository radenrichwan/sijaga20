<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AdditionalPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdditionalPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional())
            && $this->route('additional_point') instanceof AdditionalPoint;
    }

    public function rules(): array
    {
        return [
            'student_nis' => ['required', 'string', Rule::exists('students', 'nis')->where('is_active', true)],
            'points' => ['required', 'integer', 'min:1', 'max:10000'],
            'awarded_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
