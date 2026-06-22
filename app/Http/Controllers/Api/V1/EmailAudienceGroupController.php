<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailAudienceGroup;
use App\Models\EmailContact;
use App\Services\AudienceResolver;
use Illuminate\Http\Request;

class EmailAudienceGroupController extends Controller
{
    public function __construct(private AudienceResolver $resolver) {}

    public function index()
    {
        $groups = EmailAudienceGroup::orderByDesc('is_system')->orderBy('name')->get()
            ->map(fn($g) => $this->present($g));

        return response()->json(['data' => $groups]);
    }

    public function store(Request $request)
    {
        $data = $this->validateGroup($request);

        $group = EmailAudienceGroup::create([
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'type'         => $data['type'],
            'filters'      => $data['type'] === 'dynamic' ? ($data['filters'] ?? []) : null,
            'max_contacts' => $data['max_contacts'] ?? 200,
        ]);

        if ($group->type === 'static' && !empty($data['contact_ids'])) {
            $group->contacts()->sync($data['contact_ids']);
        }

        return response()->json(['data' => $this->present($group)], 201);
    }

    public function update(Request $request, EmailAudienceGroup $emailAudienceGroup)
    {
        $data = $this->validateGroup($request);

        $emailAudienceGroup->update([
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'type'         => $data['type'],
            'filters'      => $data['type'] === 'dynamic' ? ($data['filters'] ?? []) : null,
            'max_contacts' => $data['max_contacts'] ?? $emailAudienceGroup->max_contacts ?? 200,
        ]);

        if ($emailAudienceGroup->type === 'static' && array_key_exists('contact_ids', $data)) {
            $emailAudienceGroup->contacts()->sync($data['contact_ids'] ?? []);
        }

        return response()->json(['data' => $this->present($emailAudienceGroup)]);
    }

    public function destroy(EmailAudienceGroup $emailAudienceGroup)
    {
        if ($emailAudienceGroup->is_system) {
            return response()->json(['message' => 'Default groups cannot be deleted.'], 422);
        }

        $emailAudienceGroup->delete();
        return response()->json(['message' => 'Group deleted.']);
    }

    /**
     * Paginated members of a saved group (resolved from static list or filters).
     */
    public function members(EmailAudienceGroup $emailAudienceGroup)
    {
        $contacts = $this->resolver->query($emailAudienceGroup)
            ->with('tags')
            ->orderBy('full_name')
            ->paginate(25)
            ->through(fn($c) => [
                'id'        => $c->id,
                'full_name' => $c->full_name,
                'email'     => $c->email,
                'company'   => $c->company,
                'status'    => $c->status,
            ]);

        return response()->json($contacts);
    }

    /**
     * Live count + sample for an unsaved set of filters (used while building).
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'type'    => 'required|in:static,dynamic',
            'filters' => 'nullable|array',
            'contact_ids' => 'nullable|array',
        ]);

        if ($data['type'] === 'static') {
            $count = count($data['contact_ids'] ?? []);
            return response()->json(['count' => $count]);
        }

        $query = $this->resolver->applyFilters(EmailContact::query(), $data['filters'] ?? []);

        return response()->json([
            'count'  => (clone $query)->count(),
            'sample' => $query->limit(5)->get(['id', 'full_name', 'email', 'company']),
        ]);
    }

    // --- Helpers ---------------------------------------------------------

    private function validateGroup(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:500',
            'type'          => 'required|in:static,dynamic',
            'filters'       => 'nullable|array',
            'contact_ids'   => 'nullable|array',
            'contact_ids.*' => 'integer|exists:email_contacts,id',
            'max_contacts'  => 'nullable|integer|min:1|max:10000',
        ]);
    }

    private function present(EmailAudienceGroup $group): array
    {
        $count       = $this->resolver->query($group)->count();
        $maxContacts = $group->max_contacts ?? 200;

        return [
            'id'              => $group->id,
            'name'            => $group->name,
            'description'     => $group->description,
            'type'            => $group->type,
            'filters'         => $group->filters,
            'is_system'       => $group->is_system,
            'max_contacts'    => $maxContacts,
            'count'           => $count,
            'slots_remaining' => max(0, $maxContacts - $count),
            'is_full'         => $count >= $maxContacts,
            'created_at'      => $group->created_at?->toISOString(),
            'updated_at'      => $group->updated_at?->toISOString(),
        ];
    }
}
