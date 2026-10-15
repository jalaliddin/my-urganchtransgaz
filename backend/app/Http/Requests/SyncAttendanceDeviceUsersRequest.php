<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncAttendanceDeviceUsersRequest extends FormRequest
{
    /**
     * The device is already authenticated by AuthenticateAttendanceDevice.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `complete` is false when the client couldn't read the whole list
     * from the terminal; then nobody missing from it is removed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
            'complete' => ['required', 'boolean'],
            'users' => ['present', 'array', 'max:20000'],
            'users.*.user_id' => ['required', 'string', 'max:64', 'distinct'],
            'users.*.name' => ['nullable', 'string', 'max:255'],
            'users.*.card_no' => ['nullable', 'string', 'max:64'],
        ];
    }
}
