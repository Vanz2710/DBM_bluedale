<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.brevo.com/v3';

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key', '');
    }

    public function createList(string $name): int
    {
        $res = $this->post('/contacts/lists', [
            'name'     => $name,
            'folderId' => 1,
        ]);

        return $res['id'];
    }

    public function upsertContacts(array $contacts, int $listId): void
    {
        foreach ($contacts as $contact) {
            $this->post('/contacts', [
                'email'          => $contact['email'],
                'attributes'     => ['FIRSTNAME' => $contact['name'] ?? ''],
                'listIds'        => [$listId],
                'updateEnabled'  => true,
            ]);
        }
    }

    public function createCampaign(array $params): int
    {
        $senderEmail = $params['sender_email'] ?? config('services.brevo.sender_email');
        $senderName  = $params['sender_name']  ?? config('services.brevo.sender_name', 'Bluedale CRM');

        $payload = [
            'name'          => $params['name'],
            'subject'       => $params['subject'],
            'htmlContent'   => nl2br(htmlspecialchars($params['body'] ?? '')),
            'sender'        => ['name' => $senderName, 'email' => $senderEmail],
            'recipients'    => ['listIds' => [$params['list_id']]],
        ];

        if (!empty($params['preview_text'])) {
            $payload['previewText'] = $params['preview_text'];
        }

        if (!empty($params['scheduled_at'])) {
            $payload['scheduledAt'] = $params['scheduled_at'];
        }

        $res = $this->post('/emailCampaigns', $payload);

        return $res['id'];
    }

    public function sendNow(int $campaignId): void
    {
        $this->post("/emailCampaigns/{$campaignId}/sendNow", []);
    }

    public function getStats(int $campaignId): array
    {
        $res = $this->get("/emailCampaigns/{$campaignId}");

        $stats = $res['statistics']['globalStats'] ?? [];

        return [
            'sent_count' => $stats['sent']     ?? 0,
            'open_rate'  => $stats['uniqueOpens'] && $stats['sent']
                ? round($stats['uniqueOpens'] / $stats['sent'] * 100, 1)
                : 0,
            'click_rate' => $stats['uniqueClicks'] && $stats['sent']
                ? round($stats['uniqueClicks'] / $stats['sent'] * 100, 1)
                : 0,
        ];
    }

    private function post(string $path, array $body): array
    {
        $response = Http::withHeaders([
            'api-key'      => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . $path, $body);

        if ($response->failed()) {
            throw new \RuntimeException("Brevo API error ({$response->status()}): " . $response->body());
        }

        return $response->json() ?? [];
    }

    private function get(string $path): array
    {
        $response = Http::withHeaders(['api-key' => $this->apiKey])
            ->get($this->baseUrl . $path);

        if ($response->failed()) {
            throw new \RuntimeException("Brevo API error ({$response->status()}): " . $response->body());
        }

        return $response->json() ?? [];
    }
}
