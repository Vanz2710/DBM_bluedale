<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactEmail;
use Illuminate\Http\Request;

class ContactEmailController extends Controller
{
    public function index(Contact $contact)
    {
        $emails = $contact->emails()->with('user')->get();

        return response()->json(['status' => 'success', 'data' => $emails]);
    }

    public function store(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'subject'    => 'required|string|max:255',
            'body'       => 'nullable|string',
            'direction'  => 'required|in:sent,received',
            'emailed_at' => 'required|date',
        ]);

        $email = $contact->emails()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        $email->load('user');

        return response()->json(['status' => 'success', 'data' => $email], 201);
    }

    public function destroy(Contact $contact, ContactEmail $email)
    {
        abort_if($email->contact_id !== $contact->id, 404);
        $email->delete();

        return response()->json(['status' => 'success']);
    }
}
