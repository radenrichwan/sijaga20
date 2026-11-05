<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'nisn' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'string', 'max:255', 'date'],
            'end_date' => ['nullable', 'string', 'max:255', 'date', 'after_or_equal:start_date'],
            'class_name' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'semester' => ['nullable', Rule::in(['1', '2'])],
            'category_id' => ['nullable', 'integer', 'exists:violation_categories,id'],
            'type' => ['nullable', Rule::in(['violation', 'reward'])],
        ];
    }
}
