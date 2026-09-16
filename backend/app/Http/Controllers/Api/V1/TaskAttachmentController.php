<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\RecordTaskActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Resources\Api\V1\TaskAttachmentResource;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function __construct(private RecordTaskActivity $recordActivity)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $user = $request->user();
        $file = $request->file('file');
        $path = $file->store("task-attachments/{$task->id}", 'local');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $user->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $this->recordActivity->handle($task, $user, 'file_uploaded', "{$user->name} fayl yukladi: {$attachment->original_name}");

        return $this->success(new TaskAttachmentResource($attachment), 'Fayl yuklandi.', 201);
    }

    /**
     * Stream the underlying file. Never a public URL — always through this
     * authorized endpoint.
     */
    public function download(Task $task, TaskAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $task);
        abort_unless($attachment->task_id === $task->id, 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task, TaskAttachment $attachment): JsonResponse
    {
        abort_unless($attachment->task_id === $task->id, 404);

        $user = request()->user();

        if ($user->id !== $attachment->uploaded_by) {
            Gate::authorize('update', $task);
        }

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return $this->success(message: 'Fayl o\'chirildi.');
    }
}
