<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DocumentTypeResource;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $types = DocumentType::where('status', ActiveStatus::Active)->orderBy('name')->get();

        return $this->success(DocumentTypeResource::collection($types));
    }
}
