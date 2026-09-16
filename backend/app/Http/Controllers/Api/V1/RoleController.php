<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoleResource;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Read-only lookup, mirroring DocumentTypeController — currently used
     * to populate the "specific role" target picker on Announcements.
     */
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('name')->get();

        return $this->success(RoleResource::collection($roles));
    }
}
