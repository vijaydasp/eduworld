<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApplication extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New application · '.$this->application->reference_number)
            ->line('A new '.$this->application->serviceLabel().' application has been received.')
            ->line('Reference: '.$this->application->reference_number)
            ->action('Review application', route('admin.applications.show', $this->application))
            ->line('Sign in to securely view applicant information and documents.');
    }
}
