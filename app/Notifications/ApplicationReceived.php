<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceived extends Notification implements ShouldQueue
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
        return (new MailMessage)->subject('Application received · '.$this->application->reference_number)
            ->greeting('Hello '.$this->application->full_name.',')
            ->line('Thank you for applying to Edu World for '.$this->application->serviceLabel().'.')
            ->line('Your application reference is '.$this->application->reference_number.'. Please keep it for future communication.')
            ->line('Our team will review your submission and contact you if further information or documents are required.')
            ->line('Registration does not guarantee admission, employment or visa approval.')
            ->salutation('Edu World · Your Gateway to Global Opportunities');
    }
}
