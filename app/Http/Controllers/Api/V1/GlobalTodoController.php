<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactEditGrant;
use App\Models\FollowUp;
use App\Models\ToDo;
use App\Support\Csv;
use Illuminate\Http\Request;

class GlobalTodoController extends Controller
{
    public function index(Request $request)
    {
        $view     = $request->input('view', 'All');
        $date     = $request->input('date', now()->toDateString());
        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');
        $perPage  = min((int) $request->input('per_page', 100), 500);

        $query = ToDo::with(['contact.status', 'contact.type', 'task', 'user'])
            ->withCount('followUps')
            ->addSelect(['last_followup_date' => FollowUp::select('followup_date')
                ->whereColumn('todo_id', 'to_dos.id')
                ->orderByDesc('followup_date')
                ->limit(1)])
            ->orderByDesc('todo_date')
            ->orderByDesc('id');

        if ($fromDate || $toDate) {
            if ($fromDate) $query->whereDate('todo_date', '>=', $fromDate);
            if ($toDate)   $query->whereDate('todo_date', '<=', $toDate);
        } elseif ($view === 'Month') {
            $monthStart = substr($date, 0, 7) . '-01';
            $monthEnd   = date('Y-m-t', strtotime($monthStart));
            $query->whereBetween('todo_date', [$monthStart, $monthEnd]);
        } elseif ($view === 'Year') {
            $year = substr($date, 0, 4);
            $query->whereBetween('todo_date', ["{$year}-01-01", "{$year}-12-31"]);
        } elseif ($view === 'Day') {
            $query->whereDate('todo_date', $date);
        }
        // 'All' = no date filter

        if ($search = $request->input('search')) {
            $query->whereHas('contact', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($completionStatus = $request->input('completion_status')) {
            $query->where('completion_status', $completionStatus);
        }
        if ($contactId = $request->input('contact_id')) {
            $query->where('contact_id', $contactId);
        }
        if ($statusId = $request->input('status_id')) {
            $query->whereHas('contact', fn($q) => $q->where('status_id', $statusId));
        }
        if ($typeId = $request->input('type_id')) {
            $query->whereHas('contact', fn($q) => $q->where('type_id', $typeId));
        }
        if ($taskId = $request->input('task_id')) {
            $query->where('task_id', $taskId);
        }

        $todos = $query->paginate($perPage);

        $me = $request->user();
        $isAdmin = $me->hasAnyRole(['admin', 'super-admin']);
        $grantedOwnerIds = $isAdmin ? [] : ContactEditGrant::where('user_id', $me->id)
            ->pluck('target_user_id')->map(fn($id) => (int) $id)->toArray();

        $todos->getCollection()->transform(function ($t) use ($me, $isAdmin, $grantedOwnerIds) {
            return [
                'id'                => $t->id,
                'todo_date'         => $t->todo_date?->format('d-m-Y'),
                'date_created'      => $t->date_created?->format('d-m-Y'),
                'todo_remark'       => $t->todo_remark,
                'task'              => $t->task?->name,
                'task_id'           => $t->task_id,
                'user'              => $t->user?->name,
                'user_id'           => $t->user_id,
                'contact_id'        => $t->contact_id,
                'contact_name'      => $t->contact?->name,
                'status'            => $t->contact?->status?->name,
                'type'              => $t->contact?->type?->name,
                'completion_status' => $t->completion_status ?? 'pending',
                'completed_at'      => $t->completed_at?->format('d-m-Y'),
                'followups_count'   => (int) ($t->follow_ups_count ?? 0),
                'last_followup_date' => $t->last_followup_date
                    ? \Carbon\Carbon::parse($t->last_followup_date)->format('d-m-Y')
                    : null,
                'can_edit'          => $isAdmin || (int) $t->user_id === (int) $me->id
                    || \in_array((int) $t->user_id, $grantedOwnerIds),
            ];
        });

        return response()->json($todos);
    }

    // ── Active dates for calendar highlight ──────────────────────────────────
    public function activeDates(Request $request)
    {
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);
        $user  = $request->user();

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $query = ToDo::whereBetween('todo_date', [$monthStart, $monthEnd]);

        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        $dates = $query->distinct()
            ->pluck('todo_date')
            ->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : $d->format('Y-m-d'))
            ->unique()
            ->values();

        return response()->json(['dates' => $dates]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id'   => 'required|exists:contacts,id',
            'user_id'      => 'nullable|exists:users,id',
            'task_id'      => 'nullable|exists:tasks,id',
            'todo_date'    => 'required|date',
            'date_created' => 'nullable|date',
            'todo_remark'  => 'nullable|string',
        ]);

        if (empty($validated['date_created'])) {
            $validated['date_created'] = now()->toDateString();
        }

        $todo = ToDo::create($validated);

        return response()->json(['status' => 'success', 'data' => $todo->load(['task', 'user', 'contact'])], 201);
    }

    public function show(string $id)
    {
        $todo = ToDo::with(['contact.status', 'contact.type', 'task', 'user'])->findOrFail($id);
        return response()->json(['data' => $todo]);
    }

    public function update(Request $request, string $id)
    {
        $todo = ToDo::findOrFail($id);

        $validated = $request->validate([
            'user_id'      => 'nullable|exists:users,id',
            'task_id'      => 'nullable|exists:tasks,id',
            'todo_date'    => 'required|date',
            'date_created' => 'nullable|date',
            'todo_remark'  => 'nullable|string',
            'status_id'    => 'nullable|exists:contact_statuses,id',
            'type_id'      => 'nullable|exists:contact_types,id',
        ]);

        // Optionally update the company status/type
        if (!empty($validated['status_id']) || !empty($validated['type_id'])) {
            $todo->contact->update(array_filter([
                'status_id' => $validated['status_id'] ?? null,
                'type_id'   => $validated['type_id'] ?? null,
            ]));
        }

        $todo->update([
            'user_id'      => $validated['user_id'] ?? $todo->user_id,
            'task_id'      => $validated['task_id'] ?? $todo->task_id,
            'todo_date'    => $validated['todo_date'],
            'date_created' => $validated['date_created'] ?? $todo->date_created,
            'todo_remark'  => $validated['todo_remark'] ?? $todo->todo_remark,
        ]);

        return response()->json(['status' => 'success', 'data' => $todo->fresh(['task', 'user', 'contact'])]);
    }

    public function destroy(string $id)
    {
        ToDo::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function updateStatus(Request $request, string $id)
    {
        $todo = ToDo::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        $todo->completion_status = $validated['status'];
        $todo->completed_at      = $validated['status'] === 'completed' ? now() : null;
        $todo->save();

        return response()->json(['status' => 'success', 'completion_status' => $todo->completion_status]);
    }

    public function export(Request $request)
    {
        $ALLOWED = ['no','todo_date','date_created','status','type','company','user','task','remark','completion'];
        $LABELS  = [
            'no' => 'No', 'todo_date' => 'To Do Date', 'date_created' => 'Date Created',
            'status' => 'Status', 'type' => 'Type', 'company' => 'Company',
            'user' => 'User', 'task' => 'Task', 'remark' => 'Remark', 'completion' => 'Completion',
        ];
        $WIDTHS = [
            'no' => 28, 'todo_date' => 80, 'date_created' => 80, 'status' => 90,
            'type' => 60, 'company' => 200, 'user' => 100, 'task' => 110,
            'remark' => 200, 'completion' => 80,
        ];

        $requested = array_filter(explode(',', $request->input('cols', '')));
        $selected  = !empty($requested) ? array_values(array_intersect($ALLOWED, $requested)) : $ALLOWED;
        $format    = $request->input('format') === 'csv' ? 'csv' : 'xls';
        $ids       = $request->input('ids', '');

        $query = ToDo::with(['contact.status', 'contact.type', 'task', 'user'])
            ->orderByDesc('todo_date')->orderByDesc('id');

        if ($ids) {
            $query->whereIn('id', explode(',', $ids));
        } else {
            $view      = $request->input('view', 'DateRange');
            $fromDate  = $request->input('from_date', now()->toDateString());
            $toDate    = $request->input('to_date', now()->toDateString());
            $fromMonth = $request->input('from_month');
            $toMonth   = $request->input('to_month');

            if ($view === 'MonthRange' && $fromMonth && $toMonth) {
                $query->whereBetween('todo_date', [
                    $fromMonth . '-01',
                    date('Y-m-t', strtotime($toMonth . '-01')),
                ]);
            } else {
                $query->whereBetween('todo_date', [$fromDate, $toDate]);
            }

            if ($search = $request->input('search')) {
                $query->whereHas('contact', fn($q) => $q->where('name', 'like', "%{$search}%"));
            }
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }
            if ($completionStatus = $request->input('completion_status')) {
                $query->where('completion_status', $completionStatus);
            }
            if ($contactId = $request->input('contact_id')) {
                $query->where('contact_id', $contactId);
            }
            if ($statusId = $request->input('status_id')) {
                $query->whereHas('contact', fn($q) => $q->where('status_id', $statusId));
            }
            if ($typeId = $request->input('type_id')) {
                $query->whereHas('contact', fn($q) => $q->where('type_id', $typeId));
            }
            if ($taskId = $request->input('task_id')) {
                $query->where('task_id', $taskId);
            }
        }

        $getVal = fn($t, $col) => match ($col) {
            'no'          => null,
            'todo_date'   => $t->todo_date?->format('d-m-Y') ?? '',
            'date_created'=> $t->date_created?->format('d-m-Y') ?? '',
            'status'      => $t->contact?->status?->name ?? '',
            'type'        => $t->contact?->type?->name ?? '',
            'company'     => $t->contact?->name ?? '',
            'user'        => $t->user?->name ?? '',
            'task'        => $t->task?->name ?? '',
            'remark'      => $t->todo_remark ?? '',
            'completion'  => $t->completion_status ?? '',
            default       => '',
        };

        $filename = 'ToDo_Export_' . now()->format('Y-m-d') . '.' . $format;

        if ($format === 'csv') {
            return response()->stream(function () use ($query, $selected, $LABELS, $getVal) {
                $h = fopen('php://output', 'w');
                fwrite($h, "\xEF\xBB\xBF");
                fputcsv($h, array_map(fn($k) => $LABELS[$k] ?? $k, $selected));
                $no = 1;
                $query->chunk(300, function ($todos) use ($h, $selected, $getVal, &$no) {
                    foreach ($todos as $t) {
                        $row = [];
                        foreach ($selected as $col) { $row[] = $col === 'no' ? $no : $getVal($t, $col); }
                        fputcsv($h, Csv::row($row));
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
            echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>ToDos</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
            echo '<body><table style="border-collapse:collapse;"><colgroup>';
            foreach ($selected as $col) { echo '<col style="width:' . ($WIDTHS[$col] ?? 100) . 'pt">'; }
            echo '</colgroup><thead><tr>';
            foreach ($selected as $col) { echo '<th style="' . $thStyle . '">' . $esc($LABELS[$col] ?? $col) . '</th>'; }
            echo '</tr></thead><tbody>';
            $no = 1;
            $query->chunk(300, function ($todos) use (&$no, $selected, $esc, $tdStyle, $getVal) {
                foreach ($todos as $t) {
                    echo '<tr>';
                    foreach ($selected as $col) {
                        $val = $col === 'no' ? $no : $getVal($t, $col);
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
}
