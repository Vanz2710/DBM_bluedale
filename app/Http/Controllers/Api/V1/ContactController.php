<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\RoundRobinAssigner;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::with(['status', 'type', 'industry', 'category', 'area', 'user', 'territory'])
            ->withCount('incharges');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($v = $request->input('status_id'))      $query->where('status_id', $v);
        if ($v = $request->input('industry_id'))    $query->where('industry_id', $v);
        if ($v = $request->input('category_id'))    $query->where('category_id', $v);
        if ($v = $request->input('type_id'))        $query->where('type_id', $v);
        if ($v = $request->input('area_id'))        $query->where('area_id', $v);
        if ($v = $request->input('territory_id'))   $query->where('territory_id', $v);
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
            'lead_source' => 'nullable|string|max:50',
            'user_id'     => 'nullable|exists:users,id',
            'status_id'   => 'nullable|exists:contact_statuses,id',
            'type_id'     => 'nullable|exists:contact_types,id',
            'category_id' => 'nullable|exists:contact_categories,id',
            'industry_id'  => 'nullable|exists:contact_industries,id',
            'area_id'      => 'nullable|exists:contact_areas,id',
            'territory_id' => 'nullable|exists:territories,id',
            'created_at'   => 'nullable|date',
        ]);

        if (empty($validated['user_id'])) {
            $validated['user_id'] = (new RoundRobinAssigner())->nextUserId();
        }

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

        $contact->load(['status', 'type', 'industry', 'category', 'area', 'user', 'incharges']);

        WebhookDispatcher::dispatch('contact.created', [
            'id'      => $contact->id,
            'name'    => $contact->name,
            'user_id' => $contact->user_id,
            'user'    => $contact->user?->only('id', 'name'),
            'status'  => $contact->status?->only('id', 'name'),
        ]);

        return response()->json(['status' => 'success', 'data' => $contact], 201);
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
            'lead_source' => 'nullable|string|max:50',
            'user_id'     => 'nullable|exists:users,id',
            'status_id'   => 'nullable|exists:contact_statuses,id',
            'type_id'     => 'nullable|exists:contact_types,id',
            'category_id' => 'nullable|exists:contact_categories,id',
            'industry_id'  => 'nullable|exists:contact_industries,id',
            'area_id'      => 'nullable|exists:contact_areas,id',
            'territory_id' => 'nullable|exists:territories,id',
        ]);

        $contact->update($validated);

        return response()->json([
            'status' => 'success',
            'data'   => $contact->load(['status', 'type', 'industry', 'category', 'area', 'user', 'territory']),
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

    public function merge(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'super-admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'keep_id'     => 'required|exists:contacts,id',
            'merge_ids'   => 'required|array|min:1',
            'merge_ids.*' => 'exists:contacts,id',
        ]);

        $keepId   = $validated['keep_id'];
        $mergeIds = array_values(array_filter($validated['merge_ids'], fn($id) => $id != $keepId));

        if (empty($mergeIds)) {
            return response()->json(['status' => 'success', 'merged' => 0]);
        }

        DB::transaction(function () use ($keepId, $mergeIds) {
            DB::table('contact_incharges')->whereIn('contact_id', $mergeIds)->update(['contact_id' => $keepId]);
            DB::table('to_dos')->whereIn('contact_id', $mergeIds)->update(['contact_id' => $keepId]);
            DB::table('deals')->whereIn('contact_id', $mergeIds)->update(['contact_id' => $keepId]);
            DB::table('projects')->whereIn('contact_id', $mergeIds)->update(['contact_id' => $keepId]);
            Contact::whereIn('id', $mergeIds)->delete();
        });

        return response()->json(['status' => 'success', 'merged' => count($mergeIds)]);
    }
}
