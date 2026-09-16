<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Export\ExportRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const EXPORT_COLUMNS = [
        'created_at' => 'Sana',
        'user.name' => 'Foydalanuvchi',
        'action' => 'Amal',
        'module' => 'Modul',
        'entity_type' => 'Obyekt turi',
        'entity_id' => 'Obyekt ID',
        'ip_address' => 'IP manzil',
    ];

    /**
     * Display a listing of the resource. Admins can filter by user,
     * module, action, and a date range.
     */
    public function index(Request $request, ExportRecords $export): JsonResponse|Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = $this->scopedQuery($request);

        if ($format = $request->string('export')->toString()) {
            return $export->stream($query, self::EXPORT_COLUMNS, $format, 'audit-logs');
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        return $this->success(AuditLogResource::collection($logs), meta: $this->paginationMeta($logs));
    }

    private function scopedQuery(Request $request)
    {
        return AuditLog::query()
            ->with('user')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at');
    }
}
