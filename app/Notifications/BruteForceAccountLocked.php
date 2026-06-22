<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BruteForceAccountLocked extends Notification
{
    use Queueable;

    public function __construct(private User $lockedUser) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Security Alert: Account Permanently Locked — ' . $this->lockedUser->name)
            ->greeting('Hello ' . ($notifiable->name ?? 'Admin') . ',')
            ->line('An account has been **permanently locked** after too many consecutive failed login attempts.')
            ->line('**Name:** ' . $this->lockedUser->name)
            ->line('**Username:** ' . $this->lockedUser->username)
            ->line('This may indicate a brute-force attack or a user who has forgotten their password.')
            ->line('Please take one of the following actions from the Access Control panel:')
            ->line('• **Restore Access** — unlock the account and optionally reset their password via the Edit button')
            ->line('• **Delete Account** — if the account is no longer needed')
            ->action('Go to Access Control', url('/admin/rbac'))
            ->line('The user cannot log in until an administrator restores their access.');
    }
}
