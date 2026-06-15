<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ToDo;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $query = Contact::with(['status', 'type', 'industry', 'category', 'user'])
            ->select('contacts.*');

        if ($v = $request->input('search')) {
            $query->where('name', 'like', "%{$v}%");
        }
        if ($v = $request->input('status_id'))   $query->where('status_id', $v);
        if ($v = $request->input('type_id'))     $query->where('type_id', $v);
        if ($v = $request->input('industry_id')) $query->where('industry_id', $v);
        if ($v = $request->input('category_id')) $query->where('category_id', $v);
        if ($v = $request->input('user_id'))     $query->where('user_id', $v);

        $perPage = min((int) $request->input('per_page', 50), 200);
        $paginated = $query->orderBy('name')->paginate($perPage);
        $contactIds = $paginated->pluck('id');

        // Fetch completed/cancelled tasks for the current page's contacts only
        $todoRows = ToDo::whereIn('contact_id', $contactIds)
            ->whereYear('todo_date', $year)
            ->whereIn('completion_status', ['completed', 'cancelled'])
            ->with('task')
            ->orderBy('todo_date')
            ->get();

        // Build map: contact_id => month => latest done todo
        $taskMap = [];
        foreach ($todoRows as $t) {
            $month = (int) $t->todo_date->format('n');
            $taskMap[$t->contact_id][$month] = [
                'date'   => $t->todo_date->format('d-m-y'),
                'task'   => $t->task->name ?? '',
                'remark' => $t->todo_remark ?? '',
                'status' => $t->completion_status,
            ];
        }

        $data = $paginated->getCollection()->map(function ($c) use ($taskMap) {
            return [
                'id'       => $c->id,
                'name'     => $c->name,
                'user'     => $c->user?->name,
                'status'   => $c->status?->name,
                'type'     => $c->type?->name,
                'category' => $c->category?->name,
                'industry' => $c->industry?->name,
                'months'   => $taskMap[$c->id] ?? [],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'from'         => $paginated->firstItem(),
            ],
        ]);
    }
}
