<?php

namespace App\Actions\Profile;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UpdateEmployeePhotoAction
{
    private const MAX_DIMENSION = 512;

    public function handle(Employee $employee, UploadedFile $file): Employee
    {
        $manager = ImageManager::usingDriver(new Driver);

        $encoded = $manager->decode($file->getRealPath())
            ->cover(self::MAX_DIMENSION, self::MAX_DIMENSION)
            ->encodeUsingMediaType('image/jpeg', quality: 80);

        $path = "employee-photos/{$employee->id}.jpg";

        Storage::disk('local')->put($path, $encoded->toString());

        if ($employee->photo && $employee->photo !== $path) {
            Storage::disk('local')->delete($employee->photo);
        }

        $employee->update(['photo' => $path]);

        return $employee->fresh();
    }
}
