<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $ttlMinutes,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payload = [
            'appName' => (string) config('app.name', 'Ghost Room'),
            'code' => $this->code,
            'ttlMinutes' => $this->ttlMinutes,
            'logoUrl' => asset('assets/ghostup_logo.svg'),
            'supportEmail' => (string) config('ghostroom.links.support_email', ''),
            'homeUrl' => (string) config('app.url', ''),
            'recipientName' => $this->resolveRecipientName($notifiable),
        ];

        return (new MailMessage)
            ->subject('Your Ghost Room verification code')
            ->view('emails.auth.verify-code', $payload)
            ->text('emails.auth.verify-code-text', $payload);
    }

    private function resolveRecipientName(object $notifiable): string
    {
        $rawName = trim((string) data_get($notifiable, 'name', ''));

        if ($rawName === '') {
            return '';
        }

        return substr($rawName, 0, 80);
    }
}
