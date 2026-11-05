<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PointTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentObjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $studentId = $this->user()?->student?->id;

        return $this->user()?->isActive()
            && $this->user()->isSiswa()
            && $studentId !== null
            && PointTransaction::query()
                ->whereKey($this->input('point_transaction_id'))
                ->where('student_id', $studentId)
                ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'point_transaction_id' => ['required', 'integer', 'exists:point_transactions,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'supporting_note' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
