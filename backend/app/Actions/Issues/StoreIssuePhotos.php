<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class StoreIssuePhotos
{
    /** Plenty to see the problem on a phone or a monitor; a fraction of a camera original. */
    private const MAX_DIMENSION = 1600;

    /**
     * Re-encodes each photo as a JPEG no larger than MAX_DIMENSION, turned
     * upright from its EXIF orientation. Re-encoding also drops the rest
     * of the EXIF data (the phone's GPS, model, ...) — the issue already
     * carries its location explicitly.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Issue $issue, User $uploader, array $files): void
    {
        $manager = ImageManager::usingDriver(new Driver);

        foreach ($files as $file) {
            $encoded = $manager->decode($file->getRealPath())
                ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION)
                ->encodeUsingMediaType('image/jpeg', quality: 80);

            $path = "issue-photos/{$issue->id}/".Str::uuid().'.jpg';
            Storage::disk('local')->put($path, $encoded->toString());

            $issue->photos()->create(['uploaded_by' => $uploader->id, 'path' => $path]);
        }
    }
}
