<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use Illuminate\Http\Request;

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
        FollowUp::where('todo_id', $todoId)
            ->where('completion_status', 'pending')
            ->get()
            ->each(function ($f) {
                $f->completion_status = 'completed';
                $f->completed_at      = now();
                $f->save(); // fires saved hook → updates contact.last_contacted_at
            });

        return response()->json(['status' => 'success']);
    }

    public function export(Request $request)
    {
        $view      = $request->input('view', 'DateRange');
        $fromDate  = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate    = $request->input('to_date', now()->toDateString());
        $fromMonth = $request->input('from_month', now()->format('Y-m'));
        $toMonth   = $request->input('to_month', now()->format('Y-m'));
        $ids       = $request->input('ids', '');
        $actionType = $request->input('action_type');
        $todoId    = $request->input('todo_id');

        $query = FollowUp::with(['todo.contact.status', 'todo.contact.type', 'todo.user', 'todo.task']);

        if ($ids) {
            $query->whereIn('id', explode(',', $ids));
        } else {
            match ($view) {
                'MonthRange' => $query->whereBetween('followup_date', [
                    $fromMonth . '-01',
                    date('Y-m-t', strtotime($toMonth . '-01')),
                ]),
                default => $query->whereBetween('followup_date', [$fromDate, $toDate]),
            };

            if ($actionType) {
                $query->where('action_type', $actionType);
            }

            if ($todoId) {
                $query->where('todo_id', $todoId);
            }
        }

        $followUps = $query->orderByDesc('followup_date')->orderByDesc('id')->limit(10000)->get();

        $filename = 'FollowUp_Export_' . now()->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($followUps) {
            $out = fopen('php://output', 'w');
            fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['NO', 'FOLLOW-UP DATE', 'ACTION TYPE', 'COMPANY NAME', 'STATUS', 'TYPE', 'USER', 'TASK', 'NOTE']);
            $no = 1;
            foreach ($followUps as $f) {
                fputcsv($out, [
                    $no++,
                    $f->followup_date?->format('d-m-Y') ?? '-',
                    $f->action_type ?? '-',
                    $f->todo?->contact?->name ?? '-',
                    $f->todo?->contact?->status?->name ?? '-',
                    $f->todo?->contact?->type?->name ?? '-',
                    $f->todo?->user?->name ?? '-',
                    $f->todo?->task?->name ?? '-',
                    $f->note ?? '',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function format(FollowUp $f): array
    {
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
        ];
    }
}
