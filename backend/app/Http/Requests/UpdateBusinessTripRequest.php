<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateBusinessTripRequest extends StoreBusinessTripRequest
{
    /**
     * The target employee comes from the route-bound trip itself here,
     * not an `employee_id` input — reassigning a trip to someone else
     * isn't supported, so the parent's employee_id-based check doesn't
     * apply on update.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('business_trips.manage')) {
            return false;
        }

        $businessTrip = $this->route('business_trip');

        return $this->withinScope($user, $businessTrip->employee->organization_id, $businessTrip->employee->department_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'order_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
