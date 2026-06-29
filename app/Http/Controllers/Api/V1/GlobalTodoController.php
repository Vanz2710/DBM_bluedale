<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactEditGrant;
use App\Models\FollowUp;
use App\Models\ToDo;
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
            $query->whereYear('todo_date', substr($date, 0, 4))
                  ->whereMonth('todo_date', (int) substr($date, 5, 2));
        } elseif ($view === 'Year') {
            $query->whereYear('todo_date', substr($date, 0, 4));
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

        $query = ToDo::whereYear('todo_date', $year)
            ->whereMonth('todo_date', $month);

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
        $ids = $request->input('ids', '');
        $date = $request->input('date', now()->toDateString());

        $query = ToDo::with(['contact.status', 'contact.type', 'task', 'user']);

        if ($ids) {
            $query->whereIn('id', explode(',', $ids));
        } else {
            $query->whereDate('todo_date', $date);
        }

        $todos = $query->orderByDesc('todo_date')->orderByDesc('id')->limit(10000)->get();

        $filename = 'ToDo_Export_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($todos) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['NO', 'TO DO DATE', 'DATE CREATED', 'STATUS', 'TYPE', 'COMPANY NAME', 'USER', 'TASK', 'REMARK']);
            $no = 1;
            foreach ($todos as $t) {
                fputcsv($out, [
                    $no++,
                    $t->todo_date?->format('d-m-Y') ?? '-',
                    $t->date_created?->format('d-m-Y') ?? '-',
                    $t->contact?->status?->name ?? '-',
                    $t->contact?->type?->name ?? '-',
                    $t->contact?->name ?? '-',
                    $t->user?->name ?? '-',
                    $t->task?->name ?? '-',
                    $t->todo_remark ?? '',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
