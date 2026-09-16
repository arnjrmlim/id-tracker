<?php

namespace App\Http\Requests;

use App\Enums\IdStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy check done in controller; here we ensure user is admin at the request level too
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status'  => ['required', 'string', Rule::in(IdStatus::values())],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
