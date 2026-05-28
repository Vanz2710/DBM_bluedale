<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use Illuminate\Http\Request;

class EmailCampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailCampaign::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'provider'     => 'required|in:gmail,outlook',
            'sender_email' => 'nullable|email|max:255',
            'status'       => 'required|in:Draft,Scheduled,Sent',
            'schedule_at'  => 'nullable|date',
            'subject'      => 'nullable|string|max:500',
            'body'         => 'nullable|string',
            'audience'     => 'nullable|array',
            'template_id'  => 'nullable|string|max:50',
        ]);

        $campaign = EmailCampaign::create([
            'user_id' => $request->user()->id,
            ...$data,
        ]);

        return response()->json($campaign, 201);
    }

    public function update(Request $request, EmailCampaign $emailCampaign)
    {
        if ($emailCampaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'provider'     => 'sometimes|in:gmail,outlook',
            'sender_email' => 'nullable|email|max:255',
            'status'       => 'sometimes|in:Draft,Scheduled,Sent',
            'schedule_at'  => 'nullable|date',
            'subject'      => 'nullable|string|max:500',
            'body'         => 'nullable|string',
            'audience'     => 'nullable|array',
            'template_id'  => 'nullable|string|max:50',
        ]);

        $emailCampaign->update($data);

        return response()->json($emailCampaign);
    }

    public function destroy(Request $request, EmailCampaign $emailCampaign)
    {
        if ($emailCampaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $emailCampaign->delete();
        return response()->json(null, 204);
    }
}
