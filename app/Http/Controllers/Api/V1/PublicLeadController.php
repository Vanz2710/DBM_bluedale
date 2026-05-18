<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\RoundRobinAssigner;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class PublicLeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:500|unique:contacts,name',
            'pic_name'     => 'required|string|max:255',
            'pic_phone'    => 'required|string|max:50',
            'pic_email'    => 'nullable|email|max:255',
            'message'      => 'nullable|string|max:800',
        ]);

        $userId = (new RoundRobinAssigner())->nextUserId();

        $contact = Contact::create([
            'name'        => $validated['company_name'],
            'lead_source' => 'web_form',
            'remark'      => $validated['message'] ?? null,
            'user_id'     => $userId,
        ]);

        if ($validated['pic_name']) {
            $contact->incharges()->create([
                'name'         => $validated['pic_name'],
                'phone_mobile' => $validated['pic_phone'],
                'email'        => $validated['pic_email'] ?? null,
            ]);
        }

        WebhookDispatcher::dispatch('contact.created', [
            'id'      => $contact->id,
            'name'    => $contact->name,
            'user_id' => $contact->user_id,
            'source'  => 'web_form',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Your enquiry has been received. We will be in touch shortly.'], 201);
    }
}
