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
        // ID Number is now entered by ID Staff at creation time, so approval
        // no longer requires any user-supplied fields.
        return [];
    }
}
