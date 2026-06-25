<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactEditGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::with([
            'status',
            'type',
            'industry',
            'category',
            'user',
            'latestForecast.product',
            'latestForecast.result',
            'latestForecast.forecastType',
        ])->withCount(['incharges', 'forecasts']);

        if ($search = $request->input('search', $request->input('q'))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($v = $request->input('status_id'))      $query->where('status_id', $v);
        if ($v = $request->input('industry_id'))    $query->where('industry_id', $v);
        if ($v = $request->input('category_id'))    $query->where('category_id', $v);
        if ($v = $request->input('type_id'))        $query->where('type_id', $v);
        if ($request->input('user') === 'unassigned') {
            $query->whereNull('user_id');
        } elseif ($v = $request->input('user_id')) {
            $query->where('user_id', $v);
        }

        $allowedSort = ['name', 'created_at', 'updated_at'];
        $sortBy  = in_array($request->input('sort_by'), $allowedSort) ? $request->input('sort_by') : 'name';
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';

        $contacts = $query->orderBy($sortBy, $sortDir)->paginate(min((int) $request->input('per_page', 100), 500));

        $me = Auth::user();
        if (!$me->hasAnyRole(['admin', 'super-admin'])) {
            $grantedOwnerIds = ContactEditGrant::where('user_id', $me->id)
                ->pluck('target_user_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
            $contacts->getCollection()->transform(function ($c) use ($me, $grantedOwnerIds) {
                $c->can_edit = (int) $c->user_id === (int) $me->id
                    || \in_array((int) $c->user_id, $grantedOwnerIds);
                return $c;
            });
        } else {
            $contacts->getCollection()->transform(function ($c) {
                $c->can_edit = true;
                return $c;
            });
        }

        return response()->json($contacts);
    }

    public function daily(Request $request)
    {
        $date     = $request->input('date');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $perPage  = min((int) $request->input('per_page', 100), 9999);

        $query = Contact::with(['status', 'type', 'industry', 'category', 'user']);

        if ($request->boolean('with_incharges')) {
            $query->with('incharges');
        }

        if ($date) {
            $query->whereDate('created_at', $date);
        } elseif ($dateFrom || $dateTo) {
            if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
            if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($v = $request->input('user_id'))     $query->where('user_id', $v);
        if ($v = $request->input('status_id'))   $query->where('status_id', $v);
        if ($v = $request->input('type_id'))     $query->where('type_id', $v);
        if ($v = $request->input('category_id')) $query->where('category_id', $v);

        $sort = $request->input('sort', 'desc');
        $query->orderBy('created_at', in_array($sort, ['asc', 'desc']) ? $sort : 'desc');

        $contacts = $query->paginate($perPage);

        $me = Auth::user();
        if (!$me->hasAnyRole(['admin', 'super-admin'])) {
            $grantedOwnerIds = ContactEditGrant::where('user_id', $me->id)
                ->pluck('target_user_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
            $contacts->getCollection()->transform(function ($c) use ($me, $grantedOwnerIds) {
                $c->can_edit = (int) $c->user_id === (int) $me->id
                    || \in_array((int) $c->user_id, $grantedOwnerIds);
                return $c;
            });
        } else {
            $contacts->getCollection()->transform(function ($c) {
                $c->can_edit = true;
                return $c;
            });
        }

        return response()->json($contacts);
    }

    public function export(Request $request)
    {
        $sort          = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $withIncharges = $request->boolean('with_incharges');

        $ALLOWED = ['no','date_added','user','status','type','industry','company','category','address','remarks','pic_names','pic_emails','pic_mobiles','pic_offices'];
        $LABELS  = [
            'no' => 'No', 'date_added' => 'Date Added', 'user' => 'User',
            'status' => 'Status', 'type' => 'Type', 'industry' => 'Industry',
            'company' => 'Company Name', 'category' => 'Category',
            'address' => 'Address', 'remarks' => 'Remarks',
            'pic_names' => 'PIC Name(s)', 'pic_emails' => 'Email(s)',
            'pic_mobiles' => 'Mobile(s)', 'pic_offices' => 'Office(s)',
        ];
        $WIDTHS = [
            'no' => 28, 'date_added' => 72, 'user' => 90, 'status' => 68,
            'type' => 52, 'industry' => 90, 'company' => 190, 'category' => 88,
            'address' => 190, 'remarks' => 190, 'pic_names' => 140,
            'pic_emails' => 160, 'pic_mobiles' => 100, 'pic_offices' => 100,
        ];

        $requested = array_filter(explode(',', $request->input('cols', '')));
        $selected  = !empty($requested) ? array_values(array_intersect($ALLOWED, $requested)) : $ALLOWED;

        $query = Contact::with(['status', 'type', 'industry', 'category', 'user']);
        if ($withIncharges) {
            $query->with('incharges');
        }
        $query->orderBy('created_at', $sort);

        $format   = $request->input('format') === 'csv' ? 'csv' : 'xls';
        $filename = 'Contacts_' . now()->format('Y-m-d') . '.' . $format;
        $labels   = $LABELS;
        $widths   = $WIDTHS;

        $getVal = fn($c, $col) => match ($col) {
            'no'          => null, // filled by reference in chunk
            'date_added'  => $c->created_at?->format('d/m/Y') ?? '',
            'user'        => $c->user?->name ?? '',
            'status'      => $c->status?->name ?? '',
            'type'        => $c->type?->name ?? '',
            'industry'    => $c->industry?->name ?? '',
            'company'     => $c->name ?? '',
            'category'    => $c->category?->name ?? '',
            'address'     => $c->address ?? '',
            'remarks'     => $c->remark ?? '',
            'pic_names'   => ($c->incharges ?? collect())->pluck('name')->filter()->implode('; '),
            'pic_emails'  => ($c->incharges ?? collect())->pluck('email')->filter()->implode('; '),
            'pic_mobiles' => ($c->incharges ?? collect())->pluck('phone_mobile')->filter()->implode('; '),
            'pic_offices' => ($c->incharges ?? collect())->pluck('phone_office')->filter()->implode('; '),
            default       => '',
        };

        if ($format === 'csv') {
            return response()->stream(function () use ($query, $selected, $labels, $getVal) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, array_map(fn($k) => $labels[$k] ?? $k, $selected));
                $no = 1;
                $query->chunk(300, function ($contacts) use ($handle, $selected, $getVal, &$no) {
                    foreach ($contacts as $c) {
                        $row = [];
                        foreach ($selected as $col) {
                            $row[] = $col === 'no' ? $no : $getVal($c, $col);
                        }
                        fputcsv($handle, $row);
                        $no++;
                    }
                });
                fclose($handle);
            }, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'X-Accel-Buffering'   => 'no',
            ]);
        }

        return response()->stream(function () use ($query, $selected, $labels, $widths, $getVal) {
            $esc     = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $thStyle = 'font-family:Arial,sans-serif;font-size:10pt;font-weight:bold;color:#000000;background:#ffffff;border:1pt solid #000000;padding:6pt 9pt;white-space:nowrap;text-align:left;';
            $tdStyle = 'font-family:Arial,sans-serif;font-size:10pt;color:#000000;border:1pt solid #000000;padding:5pt 9pt;vertical-align:top;';

            echo "\xEF\xBB\xBF";
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Contacts</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
            echo '<body><table style="border-collapse:collapse;"><colgroup>';
            foreach ($selected as $col) { echo '<col style="width:' . ($widths[$col] ?? 100) . 'pt">'; }
            echo '</colgroup><thead><tr>';
            foreach ($selected as $col) { echo '<th style="' . $thStyle . '">' . $esc($labels[$col] ?? $col) . '</th>'; }
            echo '</tr></thead><tbody>';

            $no = 1;
            $query->chunk(300, function ($contacts) use (&$no, $selected, $esc, $tdStyle, $getVal) {
                foreach ($contacts as $c) {
                    echo '<tr>';
                    foreach ($selected as $col) {
                        $val = $col === 'no' ? $no : $getVal($c, $col);
                        echo '<td style="' . $tdStyle . '">' . $esc($val) . '</td>';
                    }
                    echo '</tr>';
                    $no++;
                }
            });

            echo '</tbody></table></body></html>';
        }, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::user();

        $validated = $request->validate([
            'name'        => 'required|string|max:500|unique:contacts,name',
            'address'     => 'nullable|string|max:255',
            'remark'      => 'nullable|string|max:800',
            'lead_source' => 'nullable|string|max:50',
            'status_id'   => 'nullable|exists:contact_statuses,id',
            'type_id'     => 'nullable|exists:contact_types,id',
            'category_id' => 'nullable|exists:contact_categories,id',
            'industry_id' => 'nullable|exists:contact_industries,id',
            'area_id'     => 'nullable|exists:contact_areas,id',
            'user_id'     => 'nullable|exists:users,id',
            'created_at'  => 'nullable|date',
        ]);

        // Admins can assign to any user; regular users always own their own contacts
        if (!$me->hasAnyRole(['admin', 'super-admin']) || empty($validated['user_id'])) {
            $validated['user_id'] = $me->id;
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

        $contact->load(['status', 'type', 'industry', 'category', 'user', 'incharges']);

        return response()->json(['status' => 'success', 'data' => $contact], 201);
    }

    public function show(string $id)
    {
        $contact = Contact::with([
            'status', 'type', 'industry', 'category', 'user', 'incharges',
            'todos' => fn($q) => $q->with(['task', 'user', 'followUps' => fn($fq) => $fq->orderByDesc('followup_date')])->orderByDesc('todo_date')->limit(200),
            'forecasts' => fn($q) => $q->with('product', 'forecastType', 'result', 'user', 'contactStatus', 'contactType')
                ->orderByDesc('forecast_date')
                ->limit(200),
        ])->findOrFail($id);

        $me = Auth::user();
        if ($me->hasAnyRole(['admin', 'super-admin'])) {
            $contact->can_edit = true;
        } else {
            $contact->can_edit = (int) $contact->user_id === (int) $me->id
                || ContactEditGrant::where('user_id', $me->id)
                    ->where('target_user_id', $contact->user_id)
                    ->exists();
        }

        return response()->json(['status' => 'success', 'data' => $contact]);
    }

    public function update(Request $request, string $id)
    {
        $contact = Contact::findOrFail($id);

        $me = Auth::user();
        if (!$me->hasAnyRole(['admin', 'super-admin'])) {
            $owned   = (int) $contact->user_id === (int) $me->id;
            $granted = !$owned && ContactEditGrant::where('user_id', $me->id)
                ->where('target_user_id', $contact->user_id)
                ->exists();
            if (!$owned && !$granted) {
                return response()->json(['message' => 'You can only edit your own contacts.'], 403);
            }
        }

        $validated = $request->validate([
            'name'        => "sometimes|required|string|max:500|unique:contacts,name,{$id}",
            'address'     => 'nullable|string|max:255',
            'remark'      => 'nullable|string|max:800',
            'lead_source' => 'nullable|string|max:50',
            'status_id'   => 'nullable|exists:contact_statuses,id',
            'type_id'     => 'nullable|exists:contact_types,id',
            'category_id' => 'nullable|exists:contact_categories,id',
            'industry_id' => 'nullable|exists:contact_industries,id',
            'area_id'     => 'nullable|exists:contact_areas,id',
            'user_id'     => 'nullable|exists:users,id',
        ]);

        // Only admins can reassign contact ownership
        if (isset($validated['user_id']) && !$me->hasAnyRole(['admin', 'super-admin'])) {
            unset($validated['user_id']);
        }

        $contact->update($validated);

        return response()->json([
            'status' => 'success',
            'data'   => $contact->load(['status', 'type', 'industry', 'category', 'user']),
        ]);
    }

    public function toggleClosed(string $id)
    {
        $contact = Contact::findOrFail($id);

        $me = Auth::user();
        if (!$me->hasAnyRole(['admin', 'super-admin'])) {
            $owned   = (int) $contact->user_id === (int) $me->id;
            $granted = !$owned && ContactEditGrant::where('user_id', $me->id)
                ->where('target_user_id', $contact->user_id)
                ->exists();
            if (!$owned && !$granted) {
                return response()->json(['message' => 'You can only edit your own contacts.'], 403);
            }
        }

        $contact->update(['is_permanently_closed' => !$contact->is_permanently_closed]);

        return response()->json([
            'status'                => 'success',
            'is_permanently_closed' => $contact->is_permanently_closed,
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

    public function findDuplicates()
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $dupeNames = DB::table('contacts')
            ->whereNull('deleted_at')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        $contacts = Contact::whereIn('name', $dupeNames)
            ->with('user:id,name', 'status:id,name')
            ->select('id', 'name', 'user_id', 'status_id', 'whatsapp_phone', 'created_at')
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->each(fn($c) => $c->phone = $c->whatsapp_phone);

        $grouped = $contacts->groupBy('name')->map(fn($g) => $g->values())->values();

        return response()->json(['data' => $grouped, 'group_count' => $grouped->count()]);
    }

    public function bulkReassign(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'from_user_id' => 'required|integer|exists:users,id',
            'to_user_id'   => 'required|integer|exists:users,id|different:from_user_id',
        ]);

        $count = Contact::where('user_id', $validated['from_user_id'])->count();
        Contact::where('user_id', $validated['from_user_id'])
            ->update(['user_id' => $validated['to_user_id']]);

        return response()->json(['status' => 'success', 'count' => $count]);
    }

    public function destroy(string $id)
    {
        $contact = Contact::findOrFail($id);

        $me = Auth::user();
        if (!$me->hasAnyRole(['admin', 'super-admin'])) {
            $owned   = (int) $contact->user_id === (int) $me->id;
            $granted = !$owned && ContactEditGrant::where('user_id', $me->id)
                ->where('target_user_id', $contact->user_id)
                ->exists();
            if (!$owned && !$granted) {
                return response()->json(['message' => 'You can only delete your own contacts.'], 403);
            }
        }

        $contact->delete();
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
            DB::table('forecasts')->whereIn('contact_id', $mergeIds)->update(['contact_id' => $keepId]);
            Contact::whereIn('id', $mergeIds)->delete();
        });

        return response()->json(['status' => 'success', 'merged' => count($mergeIds)]);
    }
}
