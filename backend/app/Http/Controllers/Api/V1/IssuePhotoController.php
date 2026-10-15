<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\IssuePhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssuePhotoController extends Controller
{
    /**
     * Streams one of the issue's photos to anyone who may see the issue.
     * Never a public URL; always through this check. Inline is safe: every
     * stored photo is a JPEG this app encoded itself (StoreIssuePhotos).
     */
    public function show(Issue $issue, IssuePhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $issue);

        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, headers: [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
