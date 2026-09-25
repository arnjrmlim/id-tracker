<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveIdRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'id_number' => ['required', 'string', 'max:50', 'unique:id_records,id_number'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_number.required' => 'ID Number is required when approving a request.',
            'id_number.unique' => 'This ID Number is already assigned to another record.',
        ];
    }
}
