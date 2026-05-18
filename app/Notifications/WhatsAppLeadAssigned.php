<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Notifications\Notification;

class WhatsAppLeadAssigned extends Notification
{
    public function __construct(
        private Contact $contact,
        private ?string $messagePreview,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'whatsapp_lead',
            'contact_id'      => $this->contact->id,
            'contact_name'    => $this->contact->name,
            'phone'           => $this->contact->whatsapp_phone,
            'message_preview' => $this->messagePreview
                ? mb_substr($this->messagePreview, 0, 200)
                : null,
            'contact_url'     => '/contacts/' . $this->contact->id,
        ];
    }
}
