<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactIncharge;
use Illuminate\Http\Request;

class ContactInchargeController extends Controller
{
    public function index(string $contactId)
    {
        $contact = Contact::findOrFail($contactId);
        return response()->json(['data' => $contact->incharges]);
    }

    public function store(Request $request, string $contactId)
    {
        Contact::findOrFail($contactId);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone_mobile' => 'nullable|string|max:50',
            'phone_office' => 'nullable|string|max:50',
        ]);

        $pic = ContactIncharge::create(array_merge($validated, ['contact_id' => $contactId]));

        return response()->json(['status' => 'success', 'data' => $pic], 201);
    }

    public function update(Request $request, string $contactId, string $id)
    {
        $pic = ContactIncharge::where('contact_id', $contactId)->findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone_mobile' => 'nullable|string|max:50',
            'phone_office' => 'nullable|string|max:50',
        ]);

        $pic->update($validated);
        return response()->json(['status' => 'success', 'data' => $pic]);
    }

    public function destroy(string $contactId, string $id)
    {
        ContactIncharge::where('contact_id', $contactId)->findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}
