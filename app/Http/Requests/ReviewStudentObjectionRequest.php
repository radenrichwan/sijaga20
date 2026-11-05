<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewStudentObjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive()
            && ($this->user()->isGuru() || $this->user()->isOperasional());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
            'decision_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
