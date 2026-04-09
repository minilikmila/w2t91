<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        if ($this->isMethod('POST') && $this->routeIs('bookings.store')) {
            $rules = [
                'resource_id' => 'required|exists:resources,id',
                'learner_id' => 'required|exists:learners,id',
                'start_time' => 'required|date|after:now',
                'end_time' => 'required|date|after:start_time',
                'idempotency_key' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:2000',
            ];
        }

        if ($this->routeIs('bookings.reschedule')) {
            $rules = [
                'start_time' => 'required|date|after:now',
                'end_time' => 'required|date|after:start_time',
            ];
        }

        if ($this->routeIs('bookings.cancel')) {
            $rules = [
                'notes' => 'nullable|string|max:2000',
            ];
        }

        if ($this->routeIs('waitlist.store')) {
            $rules = [
                'resource_id' => 'required|exists:resources,id',
                'learner_id' => 'required|exists:learners,id',
                'start_time' => 'required|date|after:now',
                'end_time' => 'required|date|after:start_time',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'start_time.after' => 'The booking start time must be in the future.',
            'end_time.after' => 'The booking end time must be after the start time.',
            'resource_id.exists' => 'The selected resource does not exist.',
            'learner_id.exists' => 'The selected learner does not exist.',
        ];
    }

    /**
     * Additional validation after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('start_time') && $this->has('end_time') && !$validator->errors()->any()) {
                $this->validateSlotIncrements($validator);
                $this->validateMinimumDuration($validator);
            }
        });
    }

    private function validateSlotIncrements($validator): void
    {
        $startTime = \Carbon\Carbon::parse($this->start_time);
        $endTime = \Carbon\Carbon::parse($this->end_time);

        if ($startTime->minute % 15 !== 0 || $startTime->second !== 0) {
            $validator->errors()->add('start_time', 'Start time must be aligned to 15-minute increments (e.g., :00, :15, :30, :45).');
        }

        if ($endTime->minute % 15 !== 0 || $endTime->second !== 0) {
            $validator->errors()->add('end_time', 'End time must be aligned to 15-minute increments (e.g., :00, :15, :30, :45).');
        }
    }

    private function validateMinimumDuration($validator): void
    {
        $startTime = \Carbon\Carbon::parse($this->start_time);
        $endTime = \Carbon\Carbon::parse($this->end_time);

        $durationMinutes = $startTime->diffInMinutes($endTime);

        if ($durationMinutes < 15) {
            $validator->errors()->add('end_time', 'Booking duration must be at least 15 minutes.');
        }
    }
}
