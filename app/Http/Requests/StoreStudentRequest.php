<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'regex:/^[0-9]{5,20}$/', Rule::unique('students', 'nis'), Rule::unique('users', 'username')],
            'nisn' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20', Rule::unique('students', 'nisn')],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'grade' => ['required', Rule::in(['X', 'XI', 'XII'])],
            'major' => ['required', 'string', 'max:100'],
            'class_name' => ['required', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
