<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppSender
{
    /**
     * Send a plain-text WhatsApp message via the Cloud API.
     * Returns the WhatsApp message ID on success, null on failure.
     */
    public function sendText(string $to, string $text): ?string
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token         = config('services.whatsapp.access_token');

        try {
            $response = Http::withToken($token)->post(
                "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['body' => $text],
                ]
            );

            $data = $response->json();

            if (!$response->successful()) {
                Log::error('WhatsApp send failed', [
                    'status' => $response->status(),
                    'body'   => $data,
                    'to'     => $to,
                ]);
                return null;
            }

            return $data['messages'][0]['id'] ?? null;

        } catch (\Throwable $e) {
            Log::error('WhatsApp send exception', ['error' => $e->getMessage(), 'to' => $to]);
            return null;
        }
    }
}
