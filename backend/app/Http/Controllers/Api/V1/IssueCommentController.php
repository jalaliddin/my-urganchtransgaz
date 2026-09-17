<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Issues\RecordIssueActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueCommentRequest;
use App\Http\Resources\Api\V1\IssueCommentResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class IssueCommentController extends Controller
{
    public function __construct(private RecordIssueActivity $recordActivity)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueCommentRequest $request, Issue $issue): JsonResponse
    {
        Gate::authorize('view', $issue);

        $user = $request->user();

        $comment = $issue->comments()->create([
            'user_id' => $user->id,
            'body' => $request->string('body')->toString(),
        ]);

        $this->recordActivity->handle($issue, $user, 'comment_added', "{$user->name} izoh qoldirdi.");

        return $this->success(new IssueCommentResource($comment->load('user')), 'Izoh qo\'shildi.', 201);
    }
}
