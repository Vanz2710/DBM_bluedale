<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactEditGrant;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    public function index(Request $request)
    {
        $view      = $request->input('view', 'DateRange');
        $fromDate  = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate    = $request->input('to_date', now()->toDateString());
        $fromMonth = $request->input('from_month', now()->format('Y-m'));
        $toMonth   = $request->input('to_month', now()->format('Y-m'));
        $perPage   = min((int) $request->input('per_page', 100), 500);

        $query = FollowUp::with(['todo.contact.status', 'todo.contact.type', 'todo.user', 'todo.task'])
            ->orderByDesc('followup_date')
            ->orderByDesc('id');

        match ($view) {
            'MonthRange' => $query->whereBetween('followup_date', [
                $fromMonth . '-01',
                date('Y-m-t', strtotime($toMonth . '-01')),
            ]),
            default => $query->whereBetween('followup_date', [$fromDate, $toDate]),
        };

        if ($actionType = $request->input('action_type')) {
            $query->where('action_type', $actionType);
        }

        if ($todoId = $request->input('todo_id')) {
            $query->where('todo_id', $todoId);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('todo.contact', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($status = $request->input('completion_status')) {
            $query->where('completion_status', $status);
        }
        if ($statusId = $request->input('status_id')) {
            $query->whereHas('todo.contact', fn($q) => $q->where('status_id', $statusId));
        }
        if ($typeId = $request->input('type_id')) {
            $query->whereHas('todo.contact', fn($q) => $q->where('type_id', $typeId));
        }
        if ($taskId = $request->input('task_id')) {
            $query->whereHas('todo', fn($q) => $q->where('task_id', $taskId));
        }

        $followUps = $query->paginate($perPage);

        $followUps->getCollection()->transform(fn($f) => $this->format($f));

        return response()->json($followUps);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'todo_id'       => 'required|exists:to_dos,id',
            'followup_date' => 'required|date',
            'action_type'   => 'nullable|string|max:100',
            'note'          => 'nullable|string',
        ]);

        $followUp = FollowUp::create($validated);

        return response()->json(['status' => 'success', 'data' => $followUp->load('todo.contact')], 201);
    }

    public function show(string $id)
    {
        $f = FollowUp::with(['todo.contact', 'todo.user', 'todo.task'])->findOrFail($id);

        return response()->json(['data' => [
            'id'            => $f->id,
            'todo_id'       => $f->todo_id,
            'followup_date' => $f->followup_date?->format('Y-m-d'),
            'action_type'   => $f->action_type,
            'note'          => $f->note,
            'contact_name'  => $f->todo?->contact?->name,
            'todo_date'     => $f->todo?->todo_date?->format('Y-m-d'),
            'task'          => $f->todo?->task?->name,
            'user'          => $f->todo?->user?->name,
        ]]);
    }

    public function update(Request $request, string $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $validated = $request->validate([
            'followup_date' => 'required|date',
            'action_type'   => 'nullable|string|max:100',
            'note'          => 'nullable|string',
        ]);

        $followUp->update($validated);

        return response()->json(['status' => 'success', 'data' => $followUp->fresh(['todo.contact'])]);
    }

    public function destroy(string $id)
    {
        FollowUp::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function updateStatus(Request $request, string $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        $followUp->completion_status = $validated['status'];
        $followUp->completed_at      = $validated['status'] === 'completed' ? now() : null;
        $followUp->save();

        return response()->json(['status' => 'success', 'completion_status' => $followUp->completion_status]);
    }

    public function bulkComplete(string $todoId)
    {
        $now = now();
        $updated = FollowUp::where('todo_id', $todoId)
            ->where('completion_status', 'pending')
            ->update([
                'completion_status' => 'completed',
                'completed_at'      => $now,
                'updated_by'        => Auth::id(),
            ]);

        // All rows share the same todo_id, so the contact is the same for every row —
        // do the last_contacted_at update once instead of relying on the per-row model event.
        if ($updated > 0) {
            $contactId = DB::table('to_dos')->where('id', $todoId)->value('contact_id');
            if ($contactId) {
                DB::table('contacts')->where('id', $contactId)->update(['last_contacted_at' => $now]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function export(Request $request)
    {
        $ALLOWED = ['no','followup_date','action_type','company','status','type','user','task','note'];
        $LABELS  = [
            'no' => 'No', 'followup_date' => 'Follow-Up Date', 'action_type' => 'Action Type',
            'company' => 'Company', 'status' => 'Status', 'type' => 'Type',
            'user' => 'User', 'task' => 'Task', 'note' => 'Note',
        ];
        $WIDTHS = [
            'no' => 28, 'followup_date' => 90, 'action_type' => 90, 'company' => 200,
            'status' => 90, 'type' => 60, 'user' => 100, 'task' => 110, 'note' => 220,
        ];

        $requested = array_filter(explode(',', $request->input('cols', '')));
        $selected  = !empty($requested) ? array_values(array_intersect($ALLOWED, $requested)) : $ALLOWED;
        $format    = $request->input('format') === 'csv' ? 'csv' : 'xls';
        $ids       = $request->input('ids', '');

        $query = FollowUp::with(['todo.contact.status', 'todo.contact.type', 'todo.user', 'todo.task'])
            ->orderByDesc('followup_date')->orderByDesc('id');

        if ($ids) {
            $query->whereIn('id', explode(',', $ids));
        } else {
            $view      = $request->input('view', 'DateRange');
            $fromDate  = $request->input('from_date', now()->startOfMonth()->toDateString());
            $toDate    = $request->input('to_date', now()->toDateString());
            $fromMonth = $request->input('from_month', now()->format('Y-m'));
            $toMonth   = $request->input('to_month', now()->format('Y-m'));

            match ($view) {
                'MonthRange' => $query->whereBetween('followup_date', [
                    $fromMonth . '-01',
                    date('Y-m-t', strtotime($toMonth . '-01')),
                ]),
                default => $query->whereBetween('followup_date', [$fromDate, $toDate]),
            };

            if ($actionType = $request->input('action_type')) {
                $query->where('action_type', $actionType);
            }
            if ($v = $request->input('completion_status')) {
                $query->where('completion_status', $v);
            }
            if ($todoId = $request->input('todo_id')) {
                $query->where('todo_id', $todoId);
            }
            if ($userId = $request->input('user_id')) {
                $query->whereHas('todo', fn($q) => $q->where('user_id', $userId));
            }
            if ($v = $request->input('status_id')) {
                $query->whereHas('todo.contact', fn($q) => $q->where('status_id', $v));
            }
            if ($v = $request->input('type_id')) {
                $query->whereHas('todo.contact', fn($q) => $q->where('type_id', $v));
            }
            if ($v = $request->input('task_id')) {
                $query->whereHas('todo', fn($q) => $q->where('task_id', $v));
            }
            if ($search = $request->input('search')) {
                $query->whereHas('todo.contact', fn($q) => $q->where('name', 'like', "%{$search}%"));
            }
        }

        $getVal = fn($f, $col) => match ($col) {
            'no'           => null,
            'followup_date'=> $f->followup_date?->format('d-m-Y') ?? '',
            'action_type'  => $f->action_type ?? '',
            'company'      => $f->todo?->contact?->name ?? '',
            'status'       => $f->todo?->contact?->status?->name ?? '',
            'type'         => $f->todo?->contact?->type?->name ?? '',
            'user'         => $f->todo?->user?->name ?? '',
            'task'         => $f->todo?->task?->name ?? '',
            'note'         => $f->note ?? '',
            default        => '',
        };

        $filename = 'FollowUp_Export_' . now()->format('Y-m-d') . '.' . $format;

        if ($format === 'csv') {
            return response()->stream(function () use ($query, $selected, $LABELS, $getVal) {
                $h = fopen('php://output', 'w');
                fwrite($h, "\xEF\xBB\xBF");
                fputcsv($h, array_map(fn($k) => $LABELS[$k] ?? $k, $selected));
                $no = 1;
                $query->chunk(300, function ($rows) use ($h, $selected, $getVal, &$no) {
                    foreach ($rows as $f) {
                        $row = [];
                        foreach ($selected as $col) { $row[] = $col === 'no' ? $no : $getVal($f, $col); }
                        fputcsv($h, $row);
                        $no++;
                    }
                });
                fclose($h);
            }, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'X-Accel-Buffering'   => 'no',
            ]);
        }

        return response()->stream(function () use ($query, $selected, $LABELS, $WIDTHS, $getVal) {
            $esc     = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $thStyle = 'font-family:Arial,sans-serif;font-size:10pt;font-weight:bold;color:#000000;background:#ffffff;border:1pt solid #000000;padding:6pt 9pt;white-space:nowrap;text-align:left;';
            $tdStyle = 'font-family:Arial,sans-serif;font-size:10pt;color:#000000;border:1pt solid #000000;padding:5pt 9pt;vertical-align:top;';
            echo "\xEF\xBB\xBF";
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>FollowUps</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
            echo '<body><table style="border-collapse:collapse;"><colgroup>';
            foreach ($selected as $col) { echo '<col style="width:' . ($WIDTHS[$col] ?? 100) . 'pt">'; }
            echo '</colgroup><thead><tr>';
            foreach ($selected as $col) { echo '<th style="' . $thStyle . '">' . $esc($LABELS[$col] ?? $col) . '</th>'; }
            echo '</tr></thead><tbody>';
            $no = 1;
            $query->chunk(300, function ($rows) use (&$no, $selected, $esc, $tdStyle, $getVal) {
                foreach ($rows as $f) {
                    echo '<tr>';
                    foreach ($selected as $col) {
                        $val = $col === 'no' ? $no : $getVal($f, $col);
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

    private ?array $_grantedOwnerIds = null;

    private function grantedOwnerIds(): array
    {
        if ($this->_grantedOwnerIds !== null) return $this->_grantedOwnerIds;
        $me = \Illuminate\Support\Facades\Auth::user();
        if (!$me || $me->hasAnyRole(['admin', 'super-admin'])) {
            return $this->_grantedOwnerIds = [];
        }
        return $this->_grantedOwnerIds = ContactEditGrant::where('user_id', $me->id)
            ->pluck('target_user_id')->map(fn($id) => (int) $id)->toArray();
    }

    private function format(FollowUp $f): array
    {
        $me      = \Illuminate\Support\Facades\Auth::user();
        $isAdmin = $me?->hasAnyRole(['admin', 'super-admin']);
        $todoUserId = (int) $f->todo?->user_id;

        return [
            'id'                => $f->id,
            'followup_date'     => $f->followup_date?->format('d-m-Y'),
            'action_type'       => $f->action_type,
            'note'              => $f->note,
            'completion_status' => $f->completion_status,
            'todo_id'           => $f->todo_id,
            'todo_date'         => $f->todo?->todo_date?->format('d-m-Y'),
            'contact_id'        => $f->todo?->contact_id,
            'contact_name'      => $f->todo?->contact?->name,
            'status'            => $f->todo?->contact?->status?->name,
            'type'              => $f->todo?->contact?->type?->name,
            'user'              => $f->todo?->user?->name,
            'task'              => $f->todo?->task?->name,
            'can_edit'          => $isAdmin
                || $todoUserId === (int) $me?->id
                || \in_array($todoUserId, $this->grantedOwnerIds()),
        ];
    }
}
