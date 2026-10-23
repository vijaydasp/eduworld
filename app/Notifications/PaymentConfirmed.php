<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array{customer_name: string, transaction_reference: string, amount_received: string, channel: string, application_reference: ?string} */
    public array $receipt;

    public function __construct(Payment $payment)
    {
        $this->receipt = $payment->only(['customer_name', 'transaction_reference', 'amount_received', 'channel']);
        $this->receipt['application_reference'] = $payment->application?->reference_number;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offline = $this->receipt['channel'] === 'offline';

        return (new MailMessage)
            ->subject(($offline ? 'Offline payment recorded — ' : 'Payment verified — ').$this->receipt['transaction_reference'])
            ->greeting('Hello '.$this->receipt['customer_name'].',')
            ->line($offline ? 'Edu World has recorded receipt of your offline payment.' : 'Your online payment has been verified with our payment provider.')
            ->line('Transaction reference: '.$this->receipt['transaction_reference'])
            ->line('Amount received: BHD '.$this->receipt['amount_received'])
            ->when($this->receipt['application_reference'], fn (MailMessage $mail) => $mail->line('Application reference: '.$this->receipt['application_reference']))
            ->line('Thank you for your payment to Edu World. Keep this reference for future communication.');
    }
}
