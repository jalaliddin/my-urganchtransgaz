<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentExpiring extends Notification
{
    use Queueable;

    /**
     * @param  int|string  $threshold  Days remaining (30, 7, 1) or "expired".
     */
    public function __construct(
        private readonly EmployeeDocument $document,
        private readonly int|string $threshold,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->threshold === 'expired'
            ? "\"{$this->document->title}\" hujjatingizning amal qilish muddati tugagan."
            : "\"{$this->document->title}\" hujjatingizning amal qilish muddati {$this->threshold} kundan so'ng tugaydi.";

        return [
            'title' => 'Hujjat muddati',
            'message' => $message,
            'document_id' => $this->document->id,
            'threshold' => $this->threshold,
        ];
    }
}
