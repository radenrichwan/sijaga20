<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional());
    }

    public function rules(): array
    {
        return [
            'student_nis' => [
                'required',
                'string',
                Rule::exists('students', 'nis')->where('is_active', true),
            ],
            'violation_category_id' => [
                'required_without:violation_category_ids',
                'integer',
                Rule::exists('violation_categories', 'id')->where('type', 'violation'),
            ],
            'violation_category_ids' => ['required_without:violation_category_id', 'array', 'min:1', 'max:20'],
            'violation_category_ids.*' => [
                'required',
                'integer',
                Rule::exists('violation_categories', 'id')->where('type', 'violation'),
            ],
            'violation_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
