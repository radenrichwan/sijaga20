<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Violation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional())
            && $this->route('violation') instanceof Violation;
    }

    public function rules(): array
    {
        return [
            'violation_category_id' => [
                'required',
                'integer',
                Rule::exists('violation_categories', 'id')->where('type', 'violation'),
            ],
            'violation_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
