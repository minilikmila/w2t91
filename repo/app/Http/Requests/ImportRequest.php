<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,xlsx,xls,txt|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'An import file is required.',
            'file.mimes' => 'The file must be a CSV, XLSX, or XLS file.',
            'file.max' => 'The file must not exceed 20MB.',
        ];
    }
}
