<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::with(['status', 'type', 'industry', 'category', 'area', 'user'])
            ->withCount('incharges');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($v = $request->input('status_id'))   $query->where('status_id', $v);
        if ($v = $request->input('industry_id')) $query->where('industry_id', $v);
        if ($v = $request->input('category_id')) $query->where('category_id', $v);
        if ($v = $request->input('type_id'))     $query->where('type_id', $v);
        if ($v = $request->input('area_id'))     $query->where('area_id', $v);
        if ($request->input('user') === 'unassigned') {
            $query->whereNull('user_id');
        } elseif ($v = $request->input('user_id')) {
            $query->where('user_id', $v);
        }

        $contacts = $query->orderBy('name')->paginate($request->input('per_page', 100));

        return response()->json($contacts);
    }

    public function daily(Request $request)
    {
        $date    = $request->input('date', now()->toDateString());
        $perPage = (int) $request->input('per_page', 100);

        $query = Contact::with(['status', 'type', 'industry', 'category', 'area', 'user'])
            ->whereDate('created_at', $date);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($v = $request->input('user_id'))   $query->where('user_id', $v);
        if ($v = $request->input('status_id')) $query->where('status_id', $v);

        $sort = $request->input('sort', 'desc');
        $query->orderBy('created_at', in_array($sort, ['asc', 'desc']) ? $sort : 'desc');

        $contacts = $query->paginate($perPage);

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:500|unique:contacts,name',
            'address'     => 'nullable|string|max:255',
            'remark'      => 'nullable|string|max:800',
            'user_id'     => 'nullable|exists:users,id',
            'status_id'   => 'nullable|exists:contact_statuses,id',
            'type_id'     => 'nullable|exists:contact_types,id',
            'category_id' => 'nullable|exists:contact_categories,id',
            'industry_id' => 'nullable|exists:contact_industries,id',
            'area_id'     => 'nullable|exists:contact_areas,id',
            'created_at'  => 'nullable|date',
        ]);

        // Allow user-specified created_at date
        $contact = new Contact($validated);
        if (!empty($validated['created_at'])) {
            $contact->created_at = $validated['created_at'];
            $contact->updated_at = $validated['created_at'];
        }
        $contact->save();

        // Create first PIC from contact fields if provided
        if ($request->filled('pic_name')) {
            $contact->incharges()->create([
                'name'         => $request->input('pic_name'),
                'email'        => $request->input('pic_email'),
                'phone_mobile' => $request->input('pic_phone'),
                'phone_office' => $request->input('pic_office'),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $contact->load(['status', 'type', 'industry', 'category', 'area', 'user', 'incharges']),
        ], 201);
    }

    public function show(string $id)
    {
        $contact = Contact::with([
            'status', 'type', 'industry', 'category', 'area', 'user', 'incharges',
            'todos' => fn($q) => $q->with('task', 'user')->orderByDesc('todo_date')->limit(200),
        ])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $contact]);
    }

    public function update(Request $request, string $id)
    {
        $contact = Contact::findOrFail($id);

        $validated = $request->validate([
            'name'        => "sometimes|required|string|max:500|unique:contacts,name,{$id}",
            'address'     => 'nullable|string|max:255',
            'remark'      => 'nullable|string|max:800',
            'user_id'     => 'nullable|exists:users,id',
            'status_id'   => 'nullable|exists:contact_statuses,id',
            'type_id'     => 'nullable|exists:contact_types,id',
            'category_id' => 'nullable|exists:contact_categories,id',
            'industry_id' => 'nullable|exists:contact_industries,id',
            'area_id'     => 'nullable|exists:contact_areas,id',
        ]);

        $contact->update($validated);

        return response()->json([
            'status' => 'success',
            'data'   => $contact->load(['status', 'type', 'industry', 'category', 'area', 'user']),
        ]);
    }

    public function checkDuplicate(Request $request)
    {
        $name = $request->input('name', '');
        $excludeId = $request->input('exclude_id');
        $query = Contact::where('name', $name);
        if ($excludeId) $query->where('id', '!=', $excludeId);
        return response()->json(['exists' => $query->exists()]);
    }

    public function destroy(string $id)
    {
        Contact::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}
