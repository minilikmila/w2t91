<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_contact' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'metadata' => 'nullable|array',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['first_name'] = 'sometimes|required|string|max:255';
            $rules['last_name'] = 'sometimes|required|string|max:255';
        }

        return $rules;
    }
}
