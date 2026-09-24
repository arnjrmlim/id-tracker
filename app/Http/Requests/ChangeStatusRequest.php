<?php

namespace App\Http\Requests;

use App\Enums\IdStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status'                => ['required', 'string', Rule::in(IdStatus::values())],
            'remarks'               => ['nullable', 'string', 'max:1000'],
            'effective_status_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_status_date.date' => 'Effective Status Date must be a valid date.',
        ];
    }
}
