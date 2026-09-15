<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\UpdateEmployeePhotoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfilePhotoRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use Illuminate\Http\JsonResponse;

class ProfilePhotoController extends Controller
{
    /**
     * Upload (or replace) the authenticated user's own profile photo.
     */
    public function store(StoreProfilePhotoRequest $request, UpdateEmployeePhotoAction $action): JsonResponse
    {
        $employee = $request->user()->employee()->firstOrFail();

        $employee = $action->handle($employee, $request->file('photo'));

        return $this->success(new EmployeeResource($employee), 'Rasm muvaffaqiyatli yuklandi.');
    }
}
