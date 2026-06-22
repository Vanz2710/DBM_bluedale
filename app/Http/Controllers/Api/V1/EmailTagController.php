<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailTag;
use Illuminate\Http\Request;

class EmailTagController extends Controller
{
    public function index()
    {
        $tags = EmailTag::withCount('contacts')->orderBy('name')->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'name'           => $t->name,
                'color'          => $t->color,
                'contacts_count' => $t->contacts_count,
            ]);

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100|unique:email_tags,name',
            'color' => 'nullable|string|max:20',
        ]);

        return response()->json(['data' => EmailTag::create($data)], 201);
    }

    public function update(Request $request, EmailTag $emailTag)
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:100|unique:email_tags,name,' . $emailTag->id,
            'color' => 'nullable|string|max:20',
        ]);

        $emailTag->update($data);

        return response()->json(['data' => $emailTag]);
    }

    public function destroy(EmailTag $emailTag)
    {
        $emailTag->delete();
        return response()->json(['message' => 'Tag deleted.']);
    }
}
