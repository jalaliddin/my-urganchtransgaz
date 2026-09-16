<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\RecordTaskActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Resources\Api\V1\TaskCommentResource;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function __construct(private RecordTaskActivity $recordActivity)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $user = $request->user();

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'body' => $request->string('body')->toString(),
        ]);

        $this->recordActivity->handle($task, $user, 'comment_added', "{$user->name} izoh qoldirdi.");

        return $this->success(new TaskCommentResource($comment->load('user')), 'Izoh qo\'shildi.', 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task, TaskComment $comment): JsonResponse
    {
        abort_unless($comment->task_id === $task->id, 404);

        $user = request()->user();

        if ($user->id !== $comment->user_id) {
            Gate::authorize('update', $task);
        }

        $comment->delete();

        return $this->success(message: 'Izoh o\'chirildi.');
    }
}
