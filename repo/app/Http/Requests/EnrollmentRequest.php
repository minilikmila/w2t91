<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'learner_id' => 'required|exists:learners,id',
                'program_name' => 'required|string|max:255',
                'approval_levels' => 'nullable|integer|min:1|max:3',
                'payment_amount' => 'nullable|numeric|min:0',
                'refund_cutoff_at' => 'nullable|date|after:now',
                'notes' => 'nullable|string|max:2000',
            ];
        }

        // Transition request
        return [
            'status' => 'required|string',
            'reason_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
