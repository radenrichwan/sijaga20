<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['violation', 'reward'])],
            'points' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'description' => ['nullable', 'string'],
            'category_id' => ['sometimes', 'integer', Rule::in([$category instanceof Category ? $category->id : 0])],
        ];
    }
}
