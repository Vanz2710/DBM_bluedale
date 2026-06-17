<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactIncharge;
use App\Models\EmailContact;
use App\Models\EmailTag;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailContactController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search'   => 'nullable|string',
            'status'   => 'nullable|in:subscribed,unsubscribed,bounced,pending',
            'tag_id'   => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = EmailContact::query()->with('tags');

        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('company', 'like', $term)
                  ->orWhere('phone', 'like', $term);
            });
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['tag_id'])) {
            $query->whereHas('tags', fn($q) => $q->where('email_tags.id', $data['tag_id']));
        }

        if (!empty($data['group_id'])) {
            $query->whereHas('groups', fn($q) => $q->where('email_audience_groups.id', $data['group_id']));
        }

        $contacts = $query->orderByDesc('created_at')
            ->paginate($data['per_page'] ?? 25)
            ->through(fn($c) => $this->present($c));

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $data = $this->validateContact($request);

        $contact = EmailContact::create([
            'full_name' => $data['full_name'] ?? null,
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'company'   => $data['company'] ?? null,
            'status'    => $data['status'] ?? 'subscribed',
            'source'    => 'manual',
        ]);

        if (array_key_exists('tag_ids', $data)) {
            $contact->tags()->sync($data['tag_ids'] ?? []);
        }

        return response()->json(['data' => $this->present($contact->fresh('tags'))], 201);
    }

    public function update(Request $request, EmailContact $emailContact)
    {
        $data = $this->validateContact($request, $emailContact->id);

        $emailContact->update(array_filter([
            'full_name' => $data['full_name'] ?? null,
            'email'     => $data['email'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'company'   => $data['company'] ?? null,
            'status'    => $data['status'] ?? null,
        ], fn($v) => $v !== null));

        if (array_key_exists('tag_ids', $data)) {
            $emailContact->tags()->sync($data['tag_ids'] ?? []);
        }

        return response()->json(['data' => $this->present($emailContact->fresh('tags'))]);
    }

    public function destroy(EmailContact $emailContact)
    {
        $emailContact->delete();
        return response()->json(['message' => 'Contact deleted.']);
    }

    /**
     * Bulk operations on a set of contact ids.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action'   => 'required|in:delete,subscribe,unsubscribe,add_tag,remove_tag,add_to_group',
            'ids'      => 'required|array|min:1',
            'ids.*'    => 'integer',
            'tag_id'   => 'nullable|integer|exists:email_tags,id',
            'group_id' => 'nullable|integer|exists:email_audience_groups,id',
        ]);

        $contacts = EmailContact::whereIn('id', $data['ids']);

        switch ($data['action']) {
            case 'delete':
                $count = $contacts->count();
                $contacts->delete();
                return response()->json(['message' => "{$count} contact(s) deleted."]);

            case 'subscribe':
                $contacts->update(['status' => 'subscribed', 'unsubscribed_at' => null]);
                break;

            case 'unsubscribe':
                $contacts->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
                break;

            case 'add_tag':
                abort_if(empty($data['tag_id']), 422, 'tag_id is required.');
                foreach ($contacts->get() as $c) {
                    $c->tags()->syncWithoutDetaching([$data['tag_id']]);
                }
                break;

            case 'remove_tag':
                abort_if(empty($data['tag_id']), 422, 'tag_id is required.');
                foreach ($contacts->get() as $c) {
                    $c->tags()->detach($data['tag_id']);
                }
                break;

            case 'add_to_group':
                abort_if(empty($data['group_id']), 422, 'group_id is required.');
                foreach ($contacts->get() as $c) {
                    $c->groups()->syncWithoutDetaching([$data['group_id']]);
                }
                break;
        }

        return response()->json(['message' => 'Done.']);
    }

    /**
     * Import contacts from an uploaded CSV. Auto-maps common header names and
     * upserts by email.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) {
            return response()->json(['message' => 'Could not read file.'], 422);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['message' => 'The file is empty.'], 422);
        }

        $map = $this->mapHeaders($header);
        if (!isset($map['email'])) {
            fclose($handle);
            return response()->json(['message' => 'No "email" column found in the CSV.'], 422);
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $email = trim($row[$map['email']] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $contact = EmailContact::firstOrNew(['email' => $email]);
            $contact->full_name = $contact->full_name ?: trim($row[$map['full_name']] ?? '') ?: null;
            $contact->phone     = $contact->phone ?: trim($row[$map['phone']] ?? '') ?: null;
            $contact->company   = $contact->company ?: trim($row[$map['company']] ?? '') ?: null;
            if (!$contact->exists) {
                $contact->source = 'import';
                $contact->status = 'subscribed';
            }
            $contact->save();
            $imported++;
        }

        fclose($handle);

        return response()->json(['message' => "Imported {$imported} contact(s), skipped {$skipped}."]);
    }

    /**
     * Export all (optionally filtered) contacts as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = EmailContact::query()->with('tags');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $filename = 'email-contacts-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Full Name', 'Email', 'Phone', 'Company', 'Status', 'Tags', 'Date Added']);

            $query->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $c) {
                    fputcsv($out, [
                        $c->full_name,
                        $c->email,
                        $c->phone,
                        $c->company,
                        $c->status,
                        $c->tags->pluck('name')->implode(', '),
                        optional($c->created_at)->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Pull CRM PICs (contact_incharges with an email) into the email contact book.
     * Existing emails are kept; only new ones are added.
     */
    public function syncFromCrm()
    {
        $existing = EmailContact::pluck('id', 'email');
        $imported = 0;
        $skipped = 0;

        ContactIncharge::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->with('contact:id,name')
            ->chunk(500, function ($chunk) use (&$existing, &$imported, &$skipped) {
                foreach ($chunk as $pic) {
                    $email = trim($pic->email);
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $existing->has($email)) {
                        $skipped++;
                        continue;
                    }

                    EmailContact::create([
                        'full_name'      => $pic->name,
                        'email'          => $email,
                        'phone'          => $pic->phone_mobile ?: $pic->phone_office,
                        'company'        => $pic->contact?->name,
                        'status'         => 'subscribed',
                        'source'         => 'crm',
                        'crm_incharge_id' => $pic->id,
                    ]);

                    $existing->put($email, true);
                    $imported++;
                }
            });

        return response()->json(['message' => "Synced {$imported} new contact(s) from CRM, skipped {$skipped} existing."]);
    }

    // --- Helpers ---------------------------------------------------------

    private function validateContact(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:email_contacts,email' . ($ignoreId ? ",{$ignoreId}" : '');

        return $request->validate([
            'full_name' => 'nullable|string|max:255',
            'email'     => ['required', 'email', 'max:255', $unique],
            'phone'     => 'nullable|string|max:50',
            'company'   => 'nullable|string|max:255',
            'status'    => 'nullable|in:subscribed,unsubscribed,bounced,pending',
            'tag_ids'   => 'nullable|array',
            'tag_ids.*' => 'integer|exists:email_tags,id',
        ]);
    }

    private function mapHeaders(array $header): array
    {
        $aliases = [
            'full_name' => ['full name', 'name', 'fullname', 'contact name'],
            'email'     => ['email', 'email address', 'e-mail'],
            'phone'     => ['phone', 'phone number', 'mobile', 'contact number'],
            'company'   => ['company', 'company name', 'organisation', 'organization'],
        ];

        $map = [];
        foreach ($header as $index => $label) {
            $key = strtolower(trim((string) $label));
            foreach ($aliases as $field => $names) {
                if (in_array($key, $names, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function present(EmailContact $c): array
    {
        return [
            'id'        => $c->id,
            'full_name' => $c->full_name,
            'email'     => $c->email,
            'phone'     => $c->phone,
            'company'   => $c->company,
            'status'    => $c->status,
            'source'    => $c->source,
            'tags'      => $c->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
            'created_at' => $c->created_at?->toISOString(),
        ];
    }
}
