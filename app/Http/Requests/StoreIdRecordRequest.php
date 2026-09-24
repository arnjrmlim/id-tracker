<?php

namespace App\Http\Requests;

use App\Models\IdRecord;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreate() ?? false;
    }

    public function rules(): array
    {
        $allowedSources = [IdRecord::SOURCE_NETWORK, IdRecord::SOURCE_UPLOAD];

        return [
            'name'              => ['required', 'string', 'max:255'],
            'position'          => ['nullable', 'string', 'max:255'],
            'employment_type'   => ['nullable', Rule::in(\App\Models\IdRecord::EMPLOYMENT_TYPES)],
            'id_number'         => ['required', 'string', 'max:50', 'unique:id_records,id_number'],
            'date_hired'        => ['nullable', 'date'],
            'birth_date'        => ['nullable', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:500'],

            // Image source selection
            'image_source'      => ['nullable', Rule::in($allowedSources)],
            'image_path'        => [
                'nullable', 'string', 'max:1000',
                Rule::requiredIf(fn() => $this->input('image_source') === IdRecord::SOURCE_NETWORK),
            ],
            'image_file'        => [
                'nullable',
                'file',
                'mimes:' . ImageUploadService::ALLOWED_EXTENSIONS,
                'max:' . ImageUploadService::MAX_KB,
                Rule::requiredIf(fn() => $this->input('image_source') === IdRecord::SOURCE_UPLOAD),
            ],

            // Signature source selection
            'signature_source'  => ['nullable', Rule::in($allowedSources)],
            'signature_path'    => [
                'nullable', 'string', 'max:1000',
                Rule::requiredIf(fn() => $this->input('signature_source') === IdRecord::SOURCE_NETWORK),
            ],
            'signature_file'    => [
                'nullable',
                'file',
                'mimes:' . ImageUploadService::ALLOWED_EXTENSIONS,
                'max:' . ImageUploadService::MAX_KB,
                Rule::requiredIf(fn() => $this->input('signature_source') === IdRecord::SOURCE_UPLOAD),
            ],
            // 'status' intentionally excluded — always forced to PENDING in controller
        ];
    }

    public function messages(): array
    {
        return [
            'image_path.required_if'    => 'Please enter the network/file path for the ID image.',
            'image_file.required_if'    => 'Please select an image file to upload.',
            'image_file.mimes'          => 'ID image must be a JPG, PNG, or WebP file.',
            'image_file.max'            => 'ID image must not exceed 5 MB.',
            'signature_path.required_if'=> 'Please enter the network/file path for the signature image.',
            'signature_file.required_if'=> 'Please select a signature file to upload.',
            'signature_file.mimes'      => 'Signature image must be a JPG, PNG, or WebP file.',
            'signature_file.max'        => 'Signature image must not exceed 5 MB.',
            'employment_type.in'        => 'Employment Type must be Employee or Agent.',
        ];
    }
}
