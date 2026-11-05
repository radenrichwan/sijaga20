<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LatenessRecord;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLatenessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional());
    }

    public function rules(): array
    {
        $studentId = Student::query()->where('nis', $this->input('student_nis'))->value('id');

        return [
            'student_nis' => [
                'required',
                'string',
                Rule::exists('students', 'nis')->where('is_active', true),
            ],
            'attendance_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                Rule::unique('lateness_records', 'attendance_date')
                    ->where(fn ($query) => $query->where('student_id', $studentId)->whereNull('deleted_at')),
            ],
            'arrival_time' => [
                'required',
                'date_format:H:i',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)
                        || ! preg_match('/^\d{2}:\d{2}$/', $value)
                        || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input('attendance_date'))) {
                        return;
                    }

                    $arrival = Carbon::createFromFormat(
                        '!Y-m-d H:i',
                        $this->input('attendance_date').' '.$value,
                        'Asia/Jakarta',
                    );
                    $deadline = Carbon::parse($this->input('attendance_date'), 'Asia/Jakarta')->setTime(6, 30);

                    if ($arrival !== false && $arrival->lessThanOrEqualTo($deadline)) {
                        $fail('Pukul 06.30 WIB masih tepat waktu. Keterlambatan dimulai pukul 06.31 WIB.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('student_nis') || $validator->errors()->has('attendance_date')) {
                    return;
                }

                $student = Student::query()->where('nis', $this->input('student_nis'))->first();
                if ($student !== null && LatenessRecord::query()
                    ->where('student_id', $student->id)
                    ->whereDate('attendance_date', $this->input('attendance_date'))
                    ->exists()) {
                    $validator->errors()->add(
                        'attendance_date',
                        'Siswa ini sudah memiliki catatan keterlambatan pada tanggal tersebut.',
                    );
                }
            },
        ];
    }
}
