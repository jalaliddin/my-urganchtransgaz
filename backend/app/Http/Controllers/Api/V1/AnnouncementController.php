<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Announcements\PublishAnnouncement;
use App\Enums\AnnouncementStatus;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource. `announcements.publish` holders
     * (central) see every announcement awaiting or past review, so they
     * can actually find drafts to act on; `announcements.create`-only
     * holders (organization-admin/hr) see just their own authored
     * announcements; everyone else sees the live, targeted audience feed.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();
        $employee = $user->employee;
        $isReviewer = $user->can('announcements.publish');
        $isAuthor = $user->can('announcements.create');

        // Plain if/else throughout, never ->when(): Spatie's QueryBuilder
        // forwards unknown methods (when() included) to the underlying
        // Eloquent builder and hands that bare builder to the callback, so
        // a ->when() callback's return value silently downgrades the whole
        // chain away from QueryBuilder — losing allowedFilters()/
        // defaultSort() if either is applied lazily.
        $query = QueryBuilder::for(Announcement::class)
            ->with(['author', 'targets'])
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-created_at');

        if ($employee) {
            $query->with(['reads' => fn ($readsQuery) => $readsQuery->where('employee_id', $employee->id)]);
        }

        if ($isAuthor || $isReviewer) {
            $query->withCount('reads');

            if (! $isReviewer) {
                $query->where('author_id', $user->id);
            }
        } else {
            $query->where('status', AnnouncementStatus::Published->value)
                ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('expire_at')->orWhere('expire_at', '>=', now()));

            if ($employee) {
                $query->audienceFor($employee);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $announcements = $query->paginate($request->integer('per_page', 15));

        return $this->success(AnnouncementResource::collection($announcements), meta: $this->paginationMeta($announcements));
    }

    /**
     * Store a newly created resource in storage. Directly published only
     * when the creator also holds `announcements.publish`; otherwise
     * always starts as a draft, regardless of a `status` sent by the client.
     */
    public function store(StoreAnnouncementRequest $request, PublishAnnouncement $publish): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $announcement = Announcement::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'author_id' => $user->id,
            'priority' => $data['priority'] ?? TaskPriority::Normal,
            'status' => AnnouncementStatus::Draft,
            'publish_at' => $data['publish_at'] ?? null,
            'expire_at' => $data['expire_at'] ?? null,
        ]);

        foreach ($data['targets'] as $target) {
            $announcement->targets()->create([
                'target_type' => $target['target_type'],
                'target_id' => $target['target_id'] ?? null,
            ]);
        }

        $this->auditLog->log('created', 'announcements', $announcement, newValues: ['title' => $announcement->title]);

        if (($data['publish_immediately'] ?? false) && $user->can('announcements.publish')) {
            $publish->handle($announcement);
        }

        return $this->success(new AnnouncementResource($announcement->load(['author', 'targets'])), 'E\'lon yaratildi.', 201);
    }

    /**
     * Display the specified resource. Marks it read for an eligible
     * audience member viewing a live announcement.
     */
    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        $announcement->load(['author', 'targets'])->loadCount('reads');

        $employee = $request->user()->employee;

        if ($employee && $announcement->isCurrentlyLive() && $announcement->appliesTo($employee)) {
            AnnouncementRead::updateOrCreate(
                ['announcement_id' => $announcement->id, 'employee_id' => $employee->id],
                ['read_at' => now()]
            );
        }

        $announcement->load(['reads' => fn ($query) => $query->when($employee, fn ($q) => $q->where('employee_id', $employee->id))]);

        return $this->success(new AnnouncementResource($announcement));
    }

    /**
     * Update the specified resource in storage. Targets are replaced
     * wholesale, the same convention as exam questions/answers.
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validated();

        $announcement->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'priority' => $data['priority'] ?? $announcement->priority,
            'publish_at' => $data['publish_at'] ?? null,
            'expire_at' => $data['expire_at'] ?? null,
        ]);

        $announcement->targets()->delete();

        foreach ($data['targets'] as $target) {
            $announcement->targets()->create([
                'target_type' => $target['target_type'],
                'target_id' => $target['target_id'] ?? null,
            ]);
        }

        $this->auditLog->log('updated', 'announcements', $announcement, newValues: $data);

        return $this->success(new AnnouncementResource($announcement->load(['author', 'targets'])), 'E\'lon yangilandi.');
    }

    /**
     * Publish the announcement now — `announcements.publish` only,
     * regardless of who authored it (centralized editorial control, §7).
     */
    public function publish(Announcement $announcement, PublishAnnouncement $publish): JsonResponse
    {
        Gate::authorize('publish', $announcement);

        if ($announcement->status !== AnnouncementStatus::Draft) {
            return $this->error('Bu e\'lon allaqachon chop etilgan yoki arxivlangan.', 409);
        }

        $publish->handle($announcement);

        return $this->success(new AnnouncementResource($announcement->load(['author', 'targets'])), 'E\'lon chop etildi.');
    }

    /**
     * Archive the announcement — the module's only end-of-life action;
     * there is no hard-delete endpoint.
     */
    public function archive(Announcement $announcement): JsonResponse
    {
        Gate::authorize('publish', $announcement);

        if ($announcement->status === AnnouncementStatus::Archived) {
            return $this->error('Bu e\'lon allaqachon arxivlangan.', 409);
        }

        $announcement->update(['status' => AnnouncementStatus::Archived]);

        $this->auditLog->log('archived', 'announcements', $announcement);

        return $this->success(new AnnouncementResource($announcement->load(['author', 'targets'])), 'E\'lon arxivlandi.');
    }

    /**
     * Upload/replace the announcement's image.
     */
    public function uploadImage(Request $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($announcement->image_path) {
            Storage::disk('local')->delete($announcement->image_path);
        }

        $path = $request->file('image')->store("announcements/{$announcement->id}", 'local');
        $announcement->update(['image_path' => $path]);

        return $this->success(new AnnouncementResource($announcement->load(['author', 'targets'])), 'Rasm yuklandi.');
    }

    /**
     * Upload/replace the announcement's attachment.
     */
    public function uploadAttachment(Request $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);

        $request->validate([
            'attachment' => ['required', 'file', 'max:10240'],
        ]);

        if ($announcement->attachment_path) {
            Storage::disk('local')->delete($announcement->attachment_path);
        }

        $file = $request->file('attachment');
        $path = $file->store("announcements/{$announcement->id}", 'local');
        $announcement->update(['attachment_path' => $path, 'attachment_name' => $file->getClientOriginalName()]);

        return $this->success(new AnnouncementResource($announcement->load(['author', 'targets'])), 'Fayl yuklandi.');
    }

    /**
     * Stream the image. Never a public URL — always through this
     * authorized endpoint, same rule as documents/photos.
     */
    public function image(Announcement $announcement): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $announcement);

        if (! $announcement->image_path || ! Storage::disk('local')->exists($announcement->image_path)) {
            return $this->error('Rasm topilmadi.', 404);
        }

        return Storage::disk('local')->response($announcement->image_path);
    }

    /**
     * Download the attachment.
     */
    public function attachment(Announcement $announcement): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $announcement);

        if (! $announcement->attachment_path || ! Storage::disk('local')->exists($announcement->attachment_path)) {
            return $this->error('Fayl topilmadi.', 404);
        }

        return Storage::disk('local')->download($announcement->attachment_path, $announcement->attachment_name);
    }
}
