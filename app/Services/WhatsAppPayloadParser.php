<?php

namespace App\Services;

class WhatsAppPayloadParser
{
    /**
     * Parse a raw Meta webhook payload into structured messages and status updates.
     *
     * Returns:
     *   messages[]   — array of inbound message data maps
     *   statuses[]   — array of delivery/read status update maps
     *   is_status_update — true when the payload contained only status entries
     */
    public function parse(array $payload): array
    {
        $result = [
            'messages'         => [],
            'statuses'         => [],
            'is_status_update' => false,
        ];

        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return $result;
        }

        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];

            if (!empty($value['statuses'])) {
                foreach ($value['statuses'] as $s) {
                    $result['statuses'][] = [
                        'message_id'   => $s['id'],
                        'status'       => $s['status'],
                        'timestamp'    => $s['timestamp'],
                        'recipient_id' => $s['recipient_id'] ?? null,
                    ];
                }
            }

            if (!empty($value['messages'])) {
                $contactIndex = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] as $msg) {
                    $waId        = $msg['from'];
                    $profile     = $contactIndex[$waId]['profile'] ?? [];
                    $displayName = $profile['name'] ?? null;
                    $type        = $msg['type'];

                    $textBody = $msg['text']['body'] ?? null;
                    $mediaId  = $msg[$type]['id'] ?? null;

                    if ($type === 'interactive') {
                        $interactive = $msg['interactive'];
                        $textBody    = $interactive['button_reply']['title']
                            ?? $interactive['list_reply']['title']
                            ?? null;
                        $mediaId = null;
                    } elseif ($type === 'button') {
                        $textBody = $msg['button']['text'] ?? null;
                        $mediaId  = null;
                    }

                    $result['messages'][] = [
                        'wa_id'        => $waId,
                        'display_name' => $displayName,
                        'message_id'   => $msg['id'],
                        'timestamp'    => $msg['timestamp'],
                        'message_type' => $type,
                        'text_body'    => $textBody,
                        'media_id'     => $mediaId,
                        'raw'          => $msg,
                    ];
                }
            }
        }

        // Mark as status-only when we got statuses but no messages
        if (!empty($result['statuses']) && empty($result['messages'])) {
            $result['is_status_update'] = true;
        }

        return $result;
    }
}
