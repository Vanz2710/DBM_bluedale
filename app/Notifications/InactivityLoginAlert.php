<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InactivityLoginAlert extends Notification
{
    use Queueable;

    public function __construct(private User $loginUser) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lastLogin = $this->loginUser->last_login_at?->format('d M Y H:i') ?? 'never';
        $daysSince = (int) ($this->loginUser->last_login_at?->diffInDays(now()) ?? 0);

        return (new MailMessage)
            ->subject('Action Required: Login Attempt After Extended Inactivity — ' . $this->loginUser->name)
            ->greeting('Hello ' . ($notifiable->name ?? 'Admin') . ',')
            ->line('A user attempted to log in after **' . $daysSince . ' days** of inactivity. Their account has been **temporarily locked** pending your review.')
            ->line('**Name:** ' . $this->loginUser->name)
            ->line('**Username:** ' . $this->loginUser->username)
            ->line('**Last active:** ' . $lastLogin)
            ->line('**Total login count:** ' . $this->loginUser->login_count)
            ->line('Please review this account and take one of the following actions from the Access Control panel:')
            ->line('• **Restore Access** — if this user should still have access to the system')
            ->line('• **Delete Account** — if this user is no longer with the organisation')
            ->action('Go to Access Control', url('/admin/rbac'))
            ->line('The user has been informed to speak with an administrator and cannot log in until access is restored.');
    }
}
