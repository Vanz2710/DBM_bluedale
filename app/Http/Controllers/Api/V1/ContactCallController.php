<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactCall;
use Illuminate\Http\Request;

class ContactCallController extends Controller
{
    public function index(Contact $contact)
    {
        $calls = $contact->calls()->with('user')->get();

        return response()->json(['status' => 'success', 'data' => $calls]);
    }

    public function store(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'direction' => 'required|in:inbound,outbound',
            'duration'  => 'nullable|integer|min:1|max:9999',
            'notes'     => 'nullable|string',
            'called_at' => 'required|date',
        ]);

        $call = $contact->calls()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        $call->load('user');

        return response()->json(['status' => 'success', 'data' => $call], 201);
    }

    public function destroy(Contact $contact, ContactCall $call)
    {
        abort_if($call->contact_id !== $contact->id, 404);
        $call->delete();

        return response()->json(['status' => 'success']);
    }
}
