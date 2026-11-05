<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && ($this->user()?->can('update', $student) ?? false);
    }

    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'nis' => [
                'required',
                'string',
                'regex:/^[0-9]{5,20}$/',
                Rule::unique('students', 'nis')->ignore($student),
                Rule::unique('users', 'username')->ignore($student->user_id),
            ],
            'nisn' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20', Rule::unique('students', 'nisn')->ignore($student)],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'grade' => ['required', Rule::in(['X', 'XI', 'XII'])],
            'major' => ['required', 'string', 'max:100'],
            'class_name' => ['required', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
