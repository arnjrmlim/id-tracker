<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectIdRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Please provide a reason for rejecting this request.',
        ];
    }
}
