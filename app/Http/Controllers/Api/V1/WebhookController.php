<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    private const VALID_EVENTS = [
        'contact.created',
        'deal.stage_changed',
        'deal.won',
        'deal.lost',
    ];

    public function index()
    {
        return response()->json(['status' => 'success', 'data' => Webhook::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'url'      => 'required|url|max:500',
            'events'   => 'required|array|min:1',
            'events.*' => 'in:' . implode(',', self::VALID_EVENTS),
            'secret'   => 'nullable|string|max:255',
            'active'   => 'boolean',
            'format'   => 'in:generic,slack',
        ]);

        $webhook = Webhook::create($validated);

        return response()->json(['status' => 'success', 'data' => $webhook], 201);
    }

    public function update(Request $request, Webhook $webhook)
    {
        $validated = $request->validate([
            'name'     => 'sometimes|required|string|max:100',
            'url'      => 'sometimes|required|url|max:500',
            'events'   => 'sometimes|required|array|min:1',
            'events.*' => 'in:' . implode(',', self::VALID_EVENTS),
            'secret'   => 'nullable|string|max:255',
            'active'   => 'boolean',
            'format'   => 'in:generic,slack',
        ]);

        $webhook->update($validated);

        return response()->json(['status' => 'success', 'data' => $webhook]);
    }

    public function destroy(Webhook $webhook)
    {
        $webhook->delete();

        return response()->json(['status' => 'success']);
    }

    public function test(Webhook $webhook)
    {
        WebhookDispatcher::send($webhook, 'webhook.test', [
            'message' => 'Test ping from Bluedale CRM',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Test payload sent to ' . $webhook->url]);
    }

    public function events()
    {
        return response()->json(['status' => 'success', 'data' => self::VALID_EVENTS]);
    }
}
