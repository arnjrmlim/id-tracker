<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled'                => ['required', 'boolean'],
            'frequency'              => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'weekly_day'             => [
                'nullable',
                'integer',
                'min:0',
                'max:6',
                Rule::requiredIf(fn () => $this->input('frequency') === 'weekly'),
            ],
            'monthly_day'            => [
                'nullable',
                'integer',
                'min:1',
                'max:28',
                Rule::requiredIf(fn () => $this->input('frequency') === 'monthly'),
            ],
            'backup_time'            => ['required', 'date_format:H:i'],
            'backup_path'            => ['required', 'string', 'max:1000'],
            'include_uploaded_files' => ['required', 'boolean'],
            'retention_days'         => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return [
            'backup_time.date_format'   => 'Backup time must be in HH:MM format.',
            'weekly_day.required_if'    => 'Please select a day of the week.',
            'monthly_day.required_if'   => 'Please enter a day of the month (1–28).',
            'backup_path.required'      => 'Please enter a backup directory path.',
            'retention_days.min'        => 'Retention must be at least 1 day.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Checkboxes are absent from POST when unchecked — normalise to boolean
        $this->merge([
            'enabled'                => $this->boolean('enabled'),
            'include_uploaded_files' => $this->boolean('include_uploaded_files'),
        ]);
    }
}
