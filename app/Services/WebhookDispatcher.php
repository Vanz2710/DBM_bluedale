<?php

namespace App\Services;

use App\Models\Webhook;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    public static function dispatch(string $event, array $data): void
    {
        $webhooks = Webhook::where('active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            static::send($webhook, $event, $data);
        }
    }

    public static function send(Webhook $webhook, string $event, array $data): void
    {
        try {
            if (($webhook->format ?? 'generic') === 'slack') {
                Http::timeout(10)->post($webhook->url, [
                    'text' => static::slackMessage($event, $data),
                ]);
                return;
            }

            $body = [
                'event'     => $event,
                'timestamp' => now()->toISOString(),
                'data'      => $data,
            ];

            $headers = ['X-CRM-Event' => $event];

            if ($webhook->secret) {
                $headers['X-CRM-Signature'] = 'sha256=' . hash_hmac('sha256', json_encode($body), $webhook->secret);
            }

            Http::timeout(10)->withHeaders($headers)->post($webhook->url, $body);
        } catch (\Throwable) {
            // Silently fail — a slow or dead endpoint must not break the main request
        }
    }

    private static function slackMessage(string $event, array $data): string
    {
        return match ($event) {
            'contact.created'    => sprintf(
                '👤 New contact: *%s* — assigned to %s',
                $data['name'] ?? 'Unknown',
                $data['assigned_to'] ?? 'Unassigned'
            ),
            'deal.stage_changed' => sprintf(
                '🔄 Deal *%s* moved to *%s*%s',
                $data['title'] ?? 'Unknown',
                $data['stage'] ?? 'Unknown',
                isset($data['previous_stage']) ? " (was {$data['previous_stage']})" : ''
            ),
            'deal.won'           => sprintf(
                '🎉 Deal Won: *%s*%s',
                $data['title'] ?? 'Unknown',
                isset($data['value']) ? ' — Value: ' . number_format((float) $data['value'], 2) : ''
            ),
            'deal.lost'          => sprintf(
                '❌ Deal Lost: *%s*%s',
                $data['title'] ?? 'Unknown',
                !empty($data['lost_reason']) ? ' — Reason: ' . $data['lost_reason'] : ''
            ),
            default              => sprintf('🔔 CRM event: *%s*', $event),
        };
    }
}
