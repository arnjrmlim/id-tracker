<?php

namespace App\Http\Requests;

use App\Enums\IdStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'     => ['required', 'array', 'min:1'],
            'ids.*'   => ['required', 'integer', 'exists:id_records,id'],
            'status'  => ['required', 'string', Rule::in(IdStatus::values())],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
