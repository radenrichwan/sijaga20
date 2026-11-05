<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LatenessRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateLatenessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional())
            && $this->route('lateness') instanceof LatenessRecord;
    }

    public function rules(): array
    {
        /** @var LatenessRecord $record */
        $record = $this->route('lateness');

        return [
            'attendance_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                Rule::unique('lateness_records', 'attendance_date')
                    ->where(fn ($query) => $query->where('student_id', $record->student_id)->whereNull('deleted_at'))
                    ->ignore($record->id),
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
}
