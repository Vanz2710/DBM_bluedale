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

    /**
     * Bulk-import contacts from a JSON payload with full settings control.
     */
    public function bulkImport(Request $request)
    {
        $data = $request->validate([
            'contacts'                    => 'required|array|min:1|max:5000',
            'contacts.*.email'            => 'required|email|max:255',
            'contacts.*.full_name'        => 'nullable|string|max:255',
            'contacts.*.phone'            => 'nullable|string|max:50',
            'contacts.*.company'          => 'nullable|string|max:255',
            'contacts.*.status'           => 'nullable|in:subscribed,unsubscribed,bounced,pending',
            'settings'                    => 'nullable|array',
            'settings.skip_duplicates'    => 'nullable|boolean',
            'settings.update_existing'    => 'nullable|boolean',
            'settings.default_status'     => 'nullable|in:subscribed,unsubscribed,bounced,pending',
            'settings.assign_group_id'    => 'nullable|integer|exists:email_audience_groups,id',
            'settings.tag_ids'            => 'nullable|array',
            'settings.tag_ids.*'          => 'integer|exists:email_tags,id',
        ]);

        $settings      = $data['settings'] ?? [];
        $skipDups      = $settings['skip_duplicates'] ?? true;
        $updateExist   = $settings['update_existing'] ?? false;
        $defaultStatus = $settings['default_status'] ?? 'subscribed';
        $groupId       = $settings['assign_group_id'] ?? null;
        $tagIds        = $settings['tag_ids'] ?? [];

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($data['contacts'] as $i => $row) {
            $email = strtolower(trim($row['email']));

            $existing = EmailContact::where('email', $email)->first();

            if ($existing) {
                if (!$updateExist) {
                    $skipped++;
                    continue;
                }

                $existing->update(array_filter([
                    'full_name' => $row['full_name'] ?: $existing->full_name,
                    'phone'     => $row['phone'] ?: $existing->phone,
                    'company'   => $row['company'] ?: $existing->company,
                    'status'    => $row['status'] ?: $existing->status,
                ], fn($v) => $v !== null && $v !== ''));

                if ($tagIds) $existing->tags()->syncWithoutDetaching($tagIds);
                if ($groupId) $existing->groups()->syncWithoutDetaching([$groupId]);

                $updated++;
                continue;
            }

            $contact = EmailContact::create([
                'full_name' => $row['full_name'] ?? null,
                'email'     => $email,
                'phone'     => $row['phone'] ?? null,
                'company'   => $row['company'] ?? null,
                'status'    => $row['status'] ?: $defaultStatus,
                'source'    => 'bulk-import',
            ]);

            if ($tagIds) $contact->tags()->sync($tagIds);
            if ($groupId) $contact->groups()->syncWithoutDetaching([$groupId]);

            $imported++;
        }

        return response()->json([
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'errors'   => $errors,
            'total'    => count($data['contacts']),
        ]);
    }

    /**
     * OCR extract stub — returns extracted contacts if Tesseract is available,
     * otherwise returns a configuration hint.
     */
    public function ocrExtract(Request $request)
    {
        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240']);

        $path = $request->file('image')->getPathname();

        // Try system Tesseract if available
        if (function_exists('exec')) {
            $output = [];
            $code   = 0;
            exec("tesseract --version 2>&1", $output, $code);

            if ($code === 0) {
                $outTxt = sys_get_temp_dir() . '/ocr_' . uniqid();
                exec("tesseract " . escapeshellarg($path) . " " . escapeshellarg($outTxt) . " 2>/dev/null");
                $text = @file_get_contents($outTxt . '.txt');
                @unlink($outTxt . '.txt');

                if ($text) {
                    $rows = $this->extractContactsFromText($text);
                    if ($rows) {
                        return response()->json([
                            'headers' => ['Full Name', 'Email', 'Phone', 'Company'],
                            'rows'    => $rows,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'message' => 'OCR is not configured on this server. Install Tesseract OCR or paste/upload CSV data instead.',
        ], 422);
    }

    private function extractContactsFromText(string $text): array
    {
        $rows   = [];
        $emails = [];
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $emailMatches);

        foreach ($emailMatches[0] as $email) {
            if (!in_array($email, $emails, true)) {
                $emails[] = $email;
                $rows[]   = ['', $email, '', ''];
            }
        }

        return $rows;
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
