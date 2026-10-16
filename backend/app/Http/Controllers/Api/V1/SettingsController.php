<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Absences\CalculateLeaveBalance;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    /**
     * The full editable settings contract: key => [group, config fallback].
     * A key an admin never touches falls back to its existing config()
     * value, so nothing changes in behavior until someone opens this page.
     *
     * @var array<string, array{0: string, 1: mixed}>
     */
    private const KEYS = [
        'organization.name' => ['organization', null],
        'organization.contact_email' => ['organization', null],
        'organization.contact_phone' => ['organization', null],
        'organization.address' => ['organization', null],
        'attendance.work_start' => ['attendance', null],
        'attendance.work_end' => ['attendance', null],
        'attendance.late_grace_minutes' => ['attendance', null],
        'attendance.early_leave_grace_minutes' => ['attendance', null],
        'attendance.working_days' => ['attendance', [1, 2, 3, 4, 5]],
        'documents.max_upload_kb' => ['documents', 10240],
        'exams.reminder_thresholds' => ['exams', [3, 1]],
        'absences.annual_leave_days' => ['absences', CalculateLeaveBalance::DEFAULT_ANNUAL_LEAVE_DAYS],
        'mobile.min_build' => ['mobile', null],
        'mobile.store_url' => ['mobile', null],
    ];

    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource, grouped by category. Timezone
     * is included read-only — changing APP_TIMEZONE at runtime mid-request
     * isn't safe, so it's never part of the editable KEYS contract.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Setting::class);

        $values = [];
        foreach (self::KEYS as $key => [, $fallback]) {
            $values[$key] = Setting::get($key, $fallback ?? config($key));
        }

        return $this->success([
            'values' => $values,
            'timezone' => config('app.timezone'),
            'has_logo' => Setting::get('organization.logo_path') !== null,
        ]);
    }

    /**
     * Update the specified resource in storage. Only known keys are
     * writable — this is a fixed contract, not an arbitrary key-value bag.
     */
    public function update(Request $request): JsonResponse
    {
        Gate::authorize('manage', Setting::class);

        // A wrong minimum build locks every mobile user out, so it gets a
        // real check; the dots in these keys are literal, hence the escapes.
        $minBuildMessage = 'Versiya raqami 0 yoki undan katta butun son bo\'lishi kerak.';
        $storeUrlMessage = 'Havola https:// bilan boshlanadigan to\'g\'ri manzil bo\'lishi kerak.';

        $request->validate([
            'values' => ['required', 'array'],
            'values.mobile\.min_build' => ['sometimes', 'integer', 'min:0', 'max:2100000000'],
            'values.mobile\.store_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
        ], [
            'values.mobile.min_build.integer' => $minBuildMessage,
            'values.mobile.min_build.min' => $minBuildMessage,
            'values.mobile.min_build.max' => $minBuildMessage,
            'values.mobile.store_url.url' => $storeUrlMessage,
            'values.mobile.store_url.max' => $storeUrlMessage,
        ]);

        $oldValues = [];
        $newValues = [];

        // Read from the input, not validate()'s return: with the escaped-dot
        // rules above it no longer returns `values`. Only KEYS are written.
        foreach ($request->input('values') as $key => $value) {
            if (! array_key_exists($key, self::KEYS)) {
                continue;
            }

            $oldValues[$key] = Setting::get($key);
            Setting::set($key, $value, self::KEYS[$key][0]);
            $newValues[$key] = $value;
        }

        $this->auditLog->log('updated', 'settings', null, $oldValues, $newValues);

        return $this->success(message: 'Sozlamalar yangilandi.');
    }

    /**
     * Upload/replace the organization logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        Gate::authorize('manage', Setting::class);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $existing = Setting::get('organization.logo_path');
        if ($existing) {
            Storage::disk('local')->delete($existing);
        }

        $path = $request->file('logo')->store('branding', 'local');
        Setting::set('organization.logo_path', $path, 'organization');

        $this->auditLog->log('updated', 'settings', null, newValues: ['organization.logo_path' => $path]);

        return $this->success(message: 'Logotip yuklandi.');
    }

    /**
     * Stream the logo. Never a public URL — same rule as every other
     * file in this app.
     */
    public function logo(): StreamedResponse|JsonResponse
    {
        $path = Setting::get('organization.logo_path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return $this->error('Logotip topilmadi.', 404);
        }

        return Storage::disk('local')->response($path);
    }
}
