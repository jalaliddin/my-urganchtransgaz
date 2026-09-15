<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\SubmitProfileUpdateAction;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\Api\V1\EmployeeChangeRequestResource;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's own employee profile.
     */
    public function show(Request $request): JsonResponse
    {
        $employee = $this->ownEmployeeOrFail($request);

        $employee->load(['organization', 'department', 'position', 'contacts']);

        return $this->success(new EmployeeResource($employee));
    }

    /**
     * Update the authenticated user's own profile. Safe fields (phone,
     * email, address, contacts) apply immediately; official-record fields
     * are bundled into a pending change request instead.
     */
    public function update(UpdateProfileRequest $request, SubmitProfileUpdateAction $action): JsonResponse
    {
        $employee = $this->ownEmployeeOrFail($request);

        $result = $action->handle($employee, $request->user(), $request->validated());

        return $this->success([
            'employee' => new EmployeeResource($result['employee']->load(['organization', 'department', 'position', 'contacts'])),
            'change_request' => $result['change_request'] ? new EmployeeChangeRequestResource($result['change_request']) : null,
        ], $result['change_request']
            ? 'Profil yangilandi. Ba\'zi o\'zgarishlar tasdiqlashni kutmoqda.'
            : 'Profil muvaffaqiyatli yangilandi.');
    }

    /**
     * Report profile completion percentage and which sections are missing.
     */
    public function completion(Request $request): JsonResponse
    {
        $employee = $this->ownEmployeeOrFail($request);

        $sections = [
            'personal_information' => filled($employee->first_name) && filled($employee->last_name)
                && filled($employee->birth_date) && filled($employee->birth_place) && filled($employee->gender),
            'contact_information' => filled($employee->phone) && filled($employee->email) && filled($employee->address),
            'employment_information' => filled($employee->department_id) && filled($employee->position_id) && filled($employee->hire_date),
            'photo' => filled($employee->photo),
            'documents' => $employee->documents()->where('status', DocumentStatus::Approved)->exists(),
            'emergency_contact' => $employee->contacts()->where('type', 'emergency')->exists(),
        ];

        $completed = count(array_filter($sections));
        $percentage = (int) round(($completed / count($sections)) * 100);

        return $this->success([
            'percentage' => $percentage,
            'sections' => $sections,
            'missing_sections' => array_keys(array_filter($sections, fn ($done) => ! $done)),
        ]);
    }

    private function ownEmployeeOrFail(Request $request): Employee
    {
        return $request->user()->employee()->firstOrFail();
    }
}
