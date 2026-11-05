<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMorningRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && $this->user()->hasRole('guru', 'operasional');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where('is_active', true),
                Rule::unique('morning_rewards', 'student_id')->where('reward_date', $this->input('reward_date')),
            ],
            'reward_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'arrival_time' => ['required', 'date_format:H:i', 'after_or_equal:04:00', 'before_or_equal:05:30'],
            'rank' => [
                'required',
                'integer',
                'between:1,10',
                Rule::unique('morning_rewards')->where('reward_date', $this->input('reward_date')),
            ],
        ];
    }
}
