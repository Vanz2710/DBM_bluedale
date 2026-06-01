<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPendingApproval extends Notification
{
    use Queueable;

    public function __construct(private User $pendingUser) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action Required: New User Login Pending Approval')
            ->greeting('Hello ' . ($notifiable->name ?? 'Admin') . ',')
            ->line('A new user has attempted to log in for the first time and requires your approval before they can access the system.')
            ->line('**Name:** ' . $this->pendingUser->name)
            ->line('**Username:** ' . $this->pendingUser->username)
            ->line('**Requested at:** ' . $this->pendingUser->access_requested_at?->format('d M Y H:i'))
            ->action('Review in Access Control', url('/admin/rbac'))
            ->line('If you do not recognise this user, do not approve them and consider reviewing your user list.');
    }
}
