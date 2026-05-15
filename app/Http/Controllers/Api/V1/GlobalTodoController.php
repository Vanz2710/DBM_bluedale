<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ToDo;
use Illuminate\Http\Request;

class GlobalTodoController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view', 'Day');
        $date = $request->input('date', now()->toDateString());
        $perPage = (int) $request->input('per_page', 100);

        $query = ToDo::with(['contact.status', 'contact.type', 'task', 'user'])
            ->orderByDesc('todo_date')
            ->orderByDesc('id');

        match ($view) {
            'Month' => $query->whereYear('todo_date', substr($date, 0, 4))
                             ->whereMonth('todo_date', (int) substr($date, 5, 2)),
            'Year'  => $query->whereYear('todo_date', substr($date, 0, 4)),
            default => $query->whereDate('todo_date', $date),
        };

        if ($search = $request->input('search')) {
            $query->whereHas('contact', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $todos = $query->paginate($perPage);

        $todos->getCollection()->transform(function ($t) {
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
            ];
        });

        return response()->json($todos);
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

        $todos = $query->orderByDesc('todo_date')->orderByDesc('id')->get();

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
