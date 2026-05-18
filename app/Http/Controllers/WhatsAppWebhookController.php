<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta webhook verification handshake (GET).
     * Meta sends hub.mode, hub.verify_token, hub.challenge as query params.
     * PHP converts dots to underscores in $_GET, so we read hub_mode etc.
     */
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode'  => $mode,
            'token' => $token ? '[present]' : '[missing]',
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Receive inbound WhatsApp events (POST).
     * Verifies X-Hub-Signature-256 against raw body + WHATSAPP_APP_SECRET,
     * then dispatches the payload to a queued job and returns 200 immediately.
     */
    public function receive(Request $request)
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');
        $secret    = config('services.whatsapp.app_secret');

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('WhatsApp webhook signature mismatch', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        $payload = json_decode($rawBody, true);

        if (!empty($payload)) {
            ProcessWhatsAppWebhook::dispatch($payload);
        }

        return response('OK', 200);
    }
}
