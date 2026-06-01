<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FirstLoginAlert extends Notification
{
    use Queueable;

    public function __construct(private User $loginUser) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New User First Login — ' . $this->loginUser->name)
            ->greeting('Hello ' . ($notifiable->name ?? 'Admin') . ',')
            ->line('A user has logged into the system for the first time.')
            ->line('**Name:** ' . $this->loginUser->name)
            ->line('**Username:** ' . $this->loginUser->username)
            ->line('**Login time:** ' . now()->format('d M Y H:i'))
            ->action('View in Access Control', url('/admin/rbac'))
            ->line('No action is required unless you do not recognise this user.');
    }
}
