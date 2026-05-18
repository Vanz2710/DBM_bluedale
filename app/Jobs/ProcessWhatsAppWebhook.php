<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Notifications\WhatsAppLeadAssigned;
use App\Services\RoundRobinAssigner;
use App\Services\WhatsAppPayloadParser;
use App\Services\WhatsAppSender;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private array $payload) {}

    public function handle(WhatsAppPayloadParser $parser, WhatsAppSender $sender): void
    {
        $parsed = $parser->parse($this->payload);

        // Apply status updates to previously logged outbound messages
        foreach ($parsed['statuses'] as $status) {
            WhatsAppMessage::where('whatsapp_message_id', $status['message_id'])
                ->update([
                    'status'    => $status['status'],
                    'timestamp' => Carbon::createFromTimestamp($status['timestamp']),
                ]);
        }

        // Nothing else to do for status-only payloads
        if ($parsed['is_status_update']) {
            return;
        }

        foreach ($parsed['messages'] as $msg) {
            $this->processInboundMessage($msg, $sender);
        }
    }

    private function processInboundMessage(array $msg, WhatsAppSender $sender): void
    {
        // Deduplicate by WhatsApp message ID
        if (WhatsAppMessage::where('whatsapp_message_id', $msg['message_id'])->exists()) {
            return;
        }

        $phone       = $this->normalizePhone($msg['wa_id']);
        $displayName = $msg['display_name'] ?: ('WhatsApp ' . $msg['wa_id']);

        [$contact, $isNew] = $this->findOrCreateContact($phone, $displayName);

        WhatsAppMessage::create([
            'contact_id'          => $contact->id,
            'channel'             => 'whatsapp',
            'direction'           => 'inbound',
            'whatsapp_message_id' => $msg['message_id'],
            'message_type'        => $msg['message_type'],
            'message_text'        => $msg['text_body'],
            'media_id'            => $msg['media_id'],
            'status'              => 'received',
            'timestamp'           => Carbon::createFromTimestamp($msg['timestamp']),
            'raw_payload'         => $msg['raw'],
        ]);

        if (!$isNew) {
            return;
        }

        // New contact: assign rep, open deal, send auto-reply, notify rep
        $this->ensureRepAssigned($contact);
        $contact->refresh();

        $this->createDealIfNone($contact);
        $this->sendAutoReply($contact, $sender);
        $this->notifyRep($contact, $msg['text_body']);
    }

    private function findOrCreateContact(string $phone, string $name): array
    {
        $existing = Contact::where('whatsapp_phone', $phone)->first();
        if ($existing) {
            return [$existing, false];
        }

        $contact = null;
        $isNew   = false;

        try {
            $contact = Contact::create([
                'name'           => $name,
                'whatsapp_phone' => $phone,
                'lead_source'    => 'whatsapp',
                'remark'         => 'WhatsApp inbound lead',
            ]);
            $isNew = true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation — a concurrent job created the contact first
            if (in_array($e->getCode(), ['23000', '23505'])) {
                $contact = Contact::where('whatsapp_phone', $phone)->firstOrFail();
                $isNew   = false;
            } else {
                throw $e;
            }
        }

        return [$contact, $isNew];
    }

    private function ensureRepAssigned(Contact $contact): void
    {
        if ($contact->user_id) {
            return;
        }

        $userId = (new RoundRobinAssigner())->nextUserId();
        if ($userId) {
            $contact->update(['user_id' => $userId]);
        }
    }

    private function createDealIfNone(Contact $contact): void
    {
        $hasOpen = Deal::where('contact_id', $contact->id)
            ->where('status', 'open')
            ->exists();

        if ($hasOpen) {
            return;
        }

        Deal::create([
            'contact_id' => $contact->id,
            'user_id'    => $contact->user_id,
            'title'      => 'WhatsApp enquiry - ' . $contact->name,
            'stage'      => 'New Lead',
            'status'     => 'open',
        ]);
    }

    private function sendAutoReply(Contact $contact, WhatsAppSender $sender): void
    {
        $text      = "Hi {$contact->name}! Thanks for reaching out. One of our team will be in touch with you shortly.";
        $messageId = null;

        try {
            $messageId = $sender->sendText($contact->whatsapp_phone, $text);
        } catch (\Throwable $e) {
            Log::error('WhatsApp auto-reply exception', [
                'contact_id' => $contact->id,
                'error'      => $e->getMessage(),
            ]);
        }

        WhatsAppMessage::create([
            'contact_id'          => $contact->id,
            'channel'             => 'whatsapp',
            'direction'           => 'outbound',
            'whatsapp_message_id' => $messageId,
            'message_type'        => 'text',
            'message_text'        => $text,
            'status'              => $messageId ? 'sent' : 'failed',
            'timestamp'           => now(),
            'raw_payload'         => [],
        ]);
    }

    private function notifyRep(Contact $contact, ?string $messagePreview): void
    {
        if (!$contact->user_id) {
            return;
        }

        $rep = User::find($contact->user_id);
        if (!$rep) {
            return;
        }

        try {
            $rep->notify(new WhatsAppLeadAssigned($contact, $messagePreview));
        } catch (\Throwable $e) {
            Log::error('WhatsApp rep notification failed', [
                'contact_id' => $contact->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        // Keep digits only; WhatsApp wa_id already includes the country code
        return preg_replace('/\D+/', '', $phone);
    }
}
