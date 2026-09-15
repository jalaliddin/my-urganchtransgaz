<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('attendance:create-device {device_id} {name} {--organization_id=}')]
#[Description('Provision a biometric/integration device and print its one-time API token.')]
class CreateAttendanceDevice extends Command
{
    public function handle(): int
    {
        $deviceId = (string) $this->argument('device_id');

        if (AttendanceDevice::where('device_id', $deviceId)->exists()) {
            $this->error("A device with device_id \"{$deviceId}\" already exists.");

            return self::FAILURE;
        }

        $organizationId = $this->option('organization_id');

        if ($organizationId && ! Organization::whereKey($organizationId)->exists()) {
            $this->error("Organization #{$organizationId} does not exist.");

            return self::FAILURE;
        }

        $plainTextToken = Str::random(40);

        AttendanceDevice::create([
            'device_id' => $deviceId,
            'name' => (string) $this->argument('name'),
            'organization_id' => $organizationId,
            'token' => hash('sha256', $plainTextToken),
            'is_active' => true,
        ]);

        $this->info('Device created. Save this token now — it will not be shown again:');
        $this->line($plainTextToken);

        return self::SUCCESS;
    }
}
