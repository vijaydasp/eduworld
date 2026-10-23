<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewEnquiry extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enquiry $enquiry)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New Edu World enquiry #'.$this->enquiry->id)
            ->line('A new enquiry about '.config('applications.services.'.$this->enquiry->service_type.'.label').' is waiting for review.')
            ->action('Review enquiry', route('admin.enquiries.show', $this->enquiry));
    }
}
