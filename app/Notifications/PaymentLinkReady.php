<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentLinkReady extends Notification implements ShouldQueue
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
        $link = route('pay', [
            'reference' => $this->application->reference_number,
            'service' => $this->application->service_type,
        ]);

        return (new MailMessage)
            ->subject('Your payment link · '.$this->application->reference_number)
            ->greeting('Hello '.$this->application->full_name.',')
            ->line('Your application for '.$this->application->serviceLabel().' has been approved.')
            ->line('Use the secure payment page to pay the service fee agreed with our team.')
            ->action('Pay the agreed service fee', $link)
            ->line('Your application reference is '.$this->application->reference_number.' and is already filled in on the payment page.')
            ->line('Please confirm the amount with our team before paying. Payment does not guarantee admission, employment or visa approval.')
            ->salutation('Edu World · Your Gateway to Global Opportunities');
    }
}
