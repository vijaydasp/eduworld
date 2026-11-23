<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PaymentRequestReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PaymentRequest $paymentRequest)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $link = URL::signedRoute(
            'pay.request.checkout',
            ['paymentRequest' => $this->paymentRequest],
        );

        if ($this->paymentRequest->isStandalone()) {
            return (new MailMessage)
                ->subject('Payment Request – '.$this->paymentRequest->description)
                ->greeting('Hello '.$this->paymentRequest->recipientName().',')
                ->line('Edu World has prepared a secure payment request for you.')
                ->line('Payment description: '.$this->paymentRequest->description)
                ->line('Amount due: BHD '.number_format((float) $this->paymentRequest->amount, 3))
                ->line('This secure link expires on '.$this->paymentRequest->expires_at->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('d M Y, H:i').' (Asia/Kolkata).')
                ->action('Pay securely via AFS', $link)
                ->line('The button opens the secure AFS (Arab Financial Services) payment form, where you can pay by card. Edu World never sees or stores your card number or security code.')
                ->line('The amount and your payment details are already confirmed on the payment page. You will not need to change the amount.')
                ->line('If you have any questions about this payment, contact us at '.Setting::getValue('email', 'info@eduworld.bh').' or '.Setting::getValue('phone', '+973 33640001').'.')
                ->line('Payment does not guarantee admission, employment or visa approval.')
                ->salutation('Edu World · Your Gateway to Global Opportunities');
        }

        $application = $this->paymentRequest->application;
        $reference = $application?->reference_number ?? 'your application';

        return (new MailMessage)
            ->subject('Payment Request for Your Application – '.$reference)
            ->greeting('Hello '.($application?->full_name ?? 'Applicant').',')
            ->line('Edu World has finalized the fee for your '.($application?->serviceLabel() ?? 'service').' application.')
            ->line('Application reference: '.$reference)
            ->line('Amount due: BHD '.number_format((float) $this->paymentRequest->amount, 3))
            ->line('Payment description: '.$this->paymentRequest->description)
            ->line('This secure link expires on '.$this->paymentRequest->expires_at->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('d M Y, H:i').' (Asia/Kolkata).')
            ->action('Pay securely via AFS', $link)
            ->line('The button opens the secure AFS (Arab Financial Services) payment form, where you can pay by card. Edu World never sees or stores your card number or security code.')
            ->line('The amount and your application details are already confirmed on the payment page. You will not need to enter an application reference or change the amount.')
            ->line('If you have any questions about this fee, contact us at '.Setting::getValue('email', 'info@eduworld.bh').' or '.Setting::getValue('phone', '+973 33640001').'.')
            ->line('Payment does not guarantee admission, employment or visa approval.')
            ->salutation('Edu World · Your Gateway to Global Opportunities');
    }
}
