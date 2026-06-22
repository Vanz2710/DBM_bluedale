<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BrevoService
{
    private string $baseUrl = 'https://api.brevo.com/v3';

    private function client()
    {
        return Http::withHeaders([
            'api-key' => config('services.brevo.api_key'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    public function createList(string $name): int
    {
        $res = $this->client()->post("{$this->baseUrl}/contacts/lists", [
            'name' => $name,
            'folderId' => 1,
        ]);

        $this->assertOk($res, 'create contact list');

        return $res->json('id');
    }

    public function upsertContacts(array $contacts, int $listId): void
    {
        foreach ($contacts as $contact) {
            $this->client()->post("{$this->baseUrl}/contacts", [
                'email' => $contact['email'],
                'attributes' => ['FIRSTNAME' => $contact['name'] ?? ''],
                'listIds' => [$listId],
                'updateEnabled' => true,
            ]);
        }
    }

    public function createCampaign(array $data): int
    {
        $payload = [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'sender' => [
                'name' => $data['sender_name'] ?? config('services.brevo.sender_name'),
                'email' => $data['sender_email'] ?? config('services.brevo.sender_email'),
            ],
            'type' => 'classic',
            'htmlContent' => $this->toHtml($data['body'] ?? '', $data['preview_text'] ?? ''),
            'recipients' => ['listIds' => [$data['list_id']]],
        ];

        if (!empty($data['scheduled_at'])) {
            $payload['scheduledAt'] = $data['scheduled_at'];
        }

        $res = $this->client()->post("{$this->baseUrl}/emailCampaigns", $payload);
        $this->assertOk($res, 'create campaign');

        return $res->json('id');
    }

    public function sendNow(int $brevoId): void
    {
        $res = $this->client()->post("{$this->baseUrl}/emailCampaigns/{$brevoId}/sendNow");
        $this->assertOk($res, 'send campaign');
    }

    public function getStats(int $brevoId): array
    {
        $res = $this->client()->get("{$this->baseUrl}/emailCampaigns/{$brevoId}");
        $this->assertOk($res, 'get campaign stats');

        $data = $res->json();
        $stats = $data['statistics']['campaignStats'][0] ?? [];

        $sent = $stats['delivered'] ?? 0;
        $opened = $stats['uniqueViews'] ?? 0;
        $clicked = $stats['uniqueClicks'] ?? 0;

        return [
            'sent_count' => $sent,
            'open_rate' => $sent > 0 ? round(($opened / $sent) * 100, 2) : null,
            'click_rate' => $sent > 0 ? round(($clicked / $sent) * 100, 2) : null,
            'status' => $data['status'] ?? null,
        ];
    }

    private function toHtml(string $body, string $preview): string
    {
        $escaped = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $previewHtml = $preview ? "<p style=\"color:#888;font-size:12px\">{$preview}</p>" : '';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#172033">
        {$previewHtml}
        <div style="line-height:1.6">{$escaped}</div>
        </body>
        </html>
        HTML;
    }

    private function assertOk(Response $res, string $action): void
    {
        if ($res->failed()) {
            $message = $res->json('message') ?? $res->body();
            throw new \RuntimeException("Brevo API failed to {$action}: {$message}");
        }
    }
}
