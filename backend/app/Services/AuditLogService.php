<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function __construct(private Request $request)
    {
        //
    }

    /**
     * Record an audit trail entry for an authenticated action.
     */
    public function log(
        string $action,
        string $module,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $causer = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $causer?->id ?? $this->request->user()?->id,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
