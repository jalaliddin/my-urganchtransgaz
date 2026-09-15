<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\UploadEmployeeDocumentAction;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectRequest;
use App\Http\Requests\StoreEmployeeDocumentRequest;
use App\Http\Resources\Api\V1\EmployeeDocumentResource;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Notifications\DocumentApproved;
use App\Notifications\DocumentRejected;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeDocument::class);

        $user = request()->user();

        $documents = QueryBuilder::for(EmployeeDocument::class)
            ->with(['documentType', 'employee'])
            ->allowedFilters(
                'status',
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('document_type_id'),
            )
            ->defaultSort('-created_at')
            ->when(
                ! $user->can('documents.view'),
                fn ($query) => $query->where('employee_id', $user->employee?->id)
            )
            ->when(
                $user->can('documents.view') && ! $user->hasCentralAccess(),
                fn ($query) => $query->whereHas('employee', function ($employeeQuery) use ($user) {
                    $employeeQuery->where('organization_id', $user->employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $employeeQuery->where('department_id', $user->employee?->department_id);
                    }
                })
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            EmployeeDocumentResource::collection($documents),
            meta: $this->paginationMeta($documents)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeDocumentRequest $request, UploadEmployeeDocumentAction $action): JsonResponse
    {
        $employeeId = $request->integer('employee_id') ?: $request->user()->employee?->id;
        $employee = Employee::findOrFail($employeeId);

        $document = $action->handle($employee, $request->user(), $request->file('file'), $request->validated());

        $this->auditLog->log('uploaded', 'documents', $document, newValues: ['title' => $document->title]);

        return $this->success(new EmployeeDocumentResource($document), 'Hujjat yuklandi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeDocument $employeeDocument): JsonResponse
    {
        Gate::authorize('view', $employeeDocument);

        $employeeDocument->load(['documentType', 'employee']);

        return $this->success(new EmployeeDocumentResource($employeeDocument));
    }

    /**
     * Stream the underlying file. Never a public URL — always through this
     * authorized endpoint.
     */
    public function download(EmployeeDocument $employeeDocument): StreamedResponse
    {
        Gate::authorize('view', $employeeDocument);

        return Storage::disk('local')->download(
            $employeeDocument->file_path,
            $employeeDocument->title.'.'.pathinfo($employeeDocument->file_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * Approve the document.
     */
    public function approve(EmployeeDocument $employeeDocument): JsonResponse
    {
        Gate::authorize('review', $employeeDocument);

        if ($employeeDocument->status !== DocumentStatus::Pending) {
            return $this->error('Bu hujjat allaqachon ko\'rib chiqilgan.', 409);
        }

        $employeeDocument->update([
            'status' => DocumentStatus::Approved,
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $employeeDocument->employee->user?->notify(new DocumentApproved($employeeDocument));

        $this->auditLog->log('approved', 'documents', $employeeDocument);

        return $this->success(new EmployeeDocumentResource($employeeDocument), 'Hujjat tasdiqlandi.');
    }

    /**
     * Reject the document with a reason (also covers "request replacement").
     */
    public function reject(RejectRequest $request, EmployeeDocument $employeeDocument): JsonResponse
    {
        Gate::authorize('review', $employeeDocument);

        if ($employeeDocument->status !== DocumentStatus::Pending) {
            return $this->error('Bu hujjat allaqachon ko\'rib chiqilgan.', 409);
        }

        $employeeDocument->update([
            'status' => DocumentStatus::Rejected,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $request->string('reason')->toString(),
        ]);

        $employeeDocument->employee->user?->notify(new DocumentRejected($employeeDocument));

        $this->auditLog->log('rejected', 'documents', $employeeDocument);

        return $this->success(new EmployeeDocumentResource($employeeDocument), 'Hujjat rad etildi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeDocument $employeeDocument): JsonResponse
    {
        Gate::authorize('delete', $employeeDocument);

        Storage::disk('local')->delete($employeeDocument->file_path);
        $employeeDocument->delete();

        $this->auditLog->log('deleted', 'documents', $employeeDocument);

        return $this->success(message: 'Hujjat o\'chirildi.');
    }
}
