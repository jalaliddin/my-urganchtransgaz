<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly EmployeeDocument $document)
    {
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
        return [
            'title' => 'Hujjat tasdiqlandi',
            'message' => "\"{$this->document->title}\" hujjatingiz tasdiqlandi.",
            'document_id' => $this->document->id,
        ];
    }
}
