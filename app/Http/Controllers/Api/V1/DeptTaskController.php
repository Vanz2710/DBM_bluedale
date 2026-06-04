<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DeptNotification;
use App\Models\DeptTask;
use App\Models\DeptTaskComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeptTaskController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $today = Carbon::today();

        $total     = DeptTask::count();
        $pending   = DeptTask::where('status', 'pending')->count();
        $inProgress= DeptTask::where('status', 'in_progress')->count();
        $waiting   = DeptTask::where('status', 'waiting_approval')->count();
        $completed = DeptTask::where('status', 'completed')->count();
        $cancelled = DeptTask::where('status', 'cancelled')->count();
        $overdue   = DeptTask::overdue()->count();

        $byDepartment = Department::withCount([
            'tasks',
            'tasks as pending_count'    => fn($q) => $q->where('status', 'pending'),
            'tasks as completed_count'  => fn($q) => $q->where('status', 'completed'),
        ])->get()->map(fn($d) => [
            'name'      => $d->name,
            'color'     => $d->color,
            'total'     => $d->tasks_count,
            'pending'   => $d->pending_count,
            'completed' => $d->completed_count,
        ]);

        $byStatus = [
            ['label' => 'Pending',          'value' => $pending,    'color' => '#94a3b8'],
            ['label' => 'In Progress',       'value' => $inProgress, 'color' => '#3b82f6'],
            ['label' => 'Waiting Approval',  'value' => $waiting,    'color' => '#f59e0b'],
            ['label' => 'Completed',         'value' => $completed,  'color' => '#10b981'],
            ['label' => 'Cancelled',         'value' => $cancelled,  'color' => '#6b7280'],
        ];

        // Weekly completion rate: last 6 weeks
        $weeklyRate = [];
        for ($i = 5; $i >= 0; $i--) {
            $weekStart = $today->copy()->startOfWeek()->subWeeks($i);
            $weekEnd   = $weekStart->copy()->endOfWeek();
            $created   = DeptTask::whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $done      = DeptTask::where('status', 'completed')->whereBetween('updated_at', [$weekStart, $weekEnd])->count();
            $weeklyRate[] = [
                'week'      => 'W'.($weekStart->weekOfYear),
                'label'     => $weekStart->format('d M'),
                'completed' => $done,
                'created'   => $created,
            ];
        }

        $recentTasks = DeptTask::with(['department', 'assignee', 'creator'])
            ->latest()->limit(8)->get()
            ->map(fn($t) => $this->formatTask($t));

        return response()->json([
            'stats' => compact('total', 'pending', 'inProgress', 'waiting', 'completed', 'cancelled', 'overdue'),
            'byDepartment' => $byDepartment,
            'byStatus'     => $byStatus,
            'weeklyRate'   => $weeklyRate,
            'recentTasks'  => $recentTasks,
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json(Department::withCount('tasks')->orderBy('name')->get());
    }

    public function users(): JsonResponse
    {
        return response()->json(
            User::select('id', 'name', 'email', 'role', 'department_id')
                ->with('department:id,name,color')
                ->orderBy('name')->get()
        );
    }

    public function notifications(Request $request): JsonResponse
    {
        $user  = $request->user();
        $items = DeptNotification::where('user_id', $user->id)
            ->with('task:id,title')
            ->latest()->limit(20)->get();
        $unread = DeptNotification::where('user_id', $user->id)->whereNull('read_at')->count();
        return response()->json(['notifications' => $items, 'unread' => $unread]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        DeptNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = DeptTask::with(['department', 'assignee:id,name,email', 'creator:id,name']);

        if ($request->filled('department_id')) {
            $q->where('department_id', $request->department_id);
        }
        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $q->overdue();
            } else {
                $q->where('status', $request->status);
            }
        }
        if ($request->filled('priority')) {
            $q->where('priority', $request->priority);
        }
        if ($request->filled('assigned_to')) {
            $q->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('search')) {
            $q->where('title', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('date_from')) {
            $q->where('due_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('due_date', '<=', $request->date_to);
        }

        $sortBy  = in_array($request->sort_by, ['created_at', 'due_date', 'priority', 'title', 'status']) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        $tasks = $q->orderBy($sortBy, $sortDir)->get()->map(fn($t) => $this->formatTask($t));

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'department_id'   => 'required|exists:departments,id',
            'assigned_to'     => 'nullable|exists:users,id',
            'priority'        => 'required|in:low,medium,high,critical',
            'status'          => 'nullable|in:pending,in_progress,waiting_approval,completed,cancelled',
            'due_date'        => 'nullable|date',
            'requires_approval' => 'boolean',
            'is_recurring'    => 'boolean',
            'recurrence_type' => 'nullable|in:daily,weekly,monthly,quarterly',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = $data['status'] ?? 'pending';

        $task = DeptTask::create($data);
        $task->load(['department', 'assignee:id,name,email', 'creator:id,name']);

        if ($task->assigned_to && $task->assigned_to !== $request->user()->id) {
            DeptNotification::create([
                'user_id' => $task->assigned_to,
                'task_id' => $task->id,
                'type'    => 'assigned',
                'message' => "You have been assigned: {$task->title}",
            ]);
        }

        return response()->json($this->formatTask($task), 201);
    }

    public function show(int $id): JsonResponse
    {
        $task = DeptTask::with([
            'department',
            'assignee:id,name,email',
            'creator:id,name',
            'comments.user:id,name',
            'attachments.user:id,name',
        ])->findOrFail($id);

        return response()->json($this->formatTask($task, true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = DeptTask::findOrFail($id);

        $data = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string',
            'department_id'     => 'sometimes|exists:departments,id',
            'assigned_to'       => 'nullable|exists:users,id',
            'priority'          => 'sometimes|in:low,medium,high,critical',
            'status'            => 'sometimes|in:pending,in_progress,waiting_approval,completed,cancelled',
            'due_date'          => 'nullable|date',
            'requires_approval' => 'boolean',
            'is_recurring'      => 'boolean',
            'recurrence_type'   => 'nullable|in:daily,weekly,monthly,quarterly',
        ]);

        $oldAssignee = $task->assigned_to;
        $task->update($data);
        $task->load(['department', 'assignee:id,name,email', 'creator:id,name']);

        if (isset($data['assigned_to']) && $data['assigned_to'] !== $oldAssignee && $task->assigned_to) {
            DeptNotification::create([
                'user_id' => $task->assigned_to,
                'task_id' => $task->id,
                'type'    => 'assigned',
                'message' => "You have been assigned: {$task->title}",
            ]);
        }

        return response()->json($this->formatTask($task));
    }

    public function destroy(int $id): JsonResponse
    {
        DeptTask::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,in_progress,waiting_approval,completed,cancelled',
        ]);

        $task = DeptTask::findOrFail($id);
        $task->update($data);
        $task->load(['department', 'assignee:id,name,email', 'creator:id,name']);

        return response()->json($this->formatTask($task));
    }

    public function addComment(Request $request, int $taskId): JsonResponse
    {
        $task = DeptTask::findOrFail($taskId);
        $data = $request->validate(['comment' => 'required|string']);

        $comment = DeptTaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'comment' => $data['comment'],
        ]);
        $comment->load('user:id,name');

        return response()->json($comment, 201);
    }

    public function deleteComment(int $taskId, int $commentId): JsonResponse
    {
        DeptTaskComment::where('task_id', $taskId)->where('id', $commentId)->firstOrFail()->delete();
        return response()->json(['ok' => true]);
    }

    public function weekly(Request $request): JsonResponse
    {
        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->week_start)->startOfDay()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $departments = Department::orderBy('id')->get();

        $tasksByDept = [];
        foreach ($departments as $dept) {
            $tasks = DeptTask::where('department_id', $dept->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with('assignee:id,name')
                ->orderBy('due_date')
                ->get()
                ->map(fn($t) => [
                    'id'          => $t->id,
                    'title'       => $t->title,
                    'due_date'    => $t->due_date?->format('d/m'),
                    'status'      => $t->status,
                    'priority'    => $t->priority,
                    'assignee'    => $t->assignee?->name,
                    'is_overdue'  => $t->is_overdue,
                ]);
            $tasksByDept[] = [
                'department' => $dept,
                'tasks'      => $tasks,
            ];
        }

        return response()->json([
            'week_start'   => $weekStart->format('d/m/Y'),
            'week_end'     => $weekEnd->format('d/m/Y'),
            'departments'  => $tasksByDept,
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
        $dateTo   = $request->filled('date_to')   ? Carbon::parse($request->date_to)   : Carbon::now();

        $tasks = DeptTask::with(['department', 'assignee:id,name', 'creator:id,name'])
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->orderBy('department_id')->orderBy('priority')->get();

        $byDept = $tasks->groupBy('department_id')->map(function ($group) {
            $dept = $group->first()->department;
            return [
                'department' => $dept->name,
                'color'      => $dept->color,
                'total'      => $group->count(),
                'completed'  => $group->where('status', 'completed')->count(),
                'pending'    => $group->where('status', 'pending')->count(),
                'overdue'    => $group->filter(fn($t) => $t->is_overdue)->count(),
                'tasks'      => $group->map(fn($t) => $this->formatTask($t))->values(),
            ];
        })->values();

        return response()->json([
            'date_from'   => $dateFrom->format('d M Y'),
            'date_to'     => $dateTo->format('d M Y'),
            'total'       => $tasks->count(),
            'completed'   => $tasks->where('status', 'completed')->count(),
            'pending'     => $tasks->where('status', 'pending')->count(),
            'overdue'     => $tasks->filter(fn($t) => $t->is_overdue)->count(),
            'byDepartment'=> $byDept,
        ]);
    }

    private function formatTask(DeptTask $task, bool $withDetails = false): array
    {
        $data = [
            'id'              => $task->id,
            'title'           => $task->title,
            'description'     => $task->description,
            'department_id'   => $task->department_id,
            'department'      => $task->department ? ['id' => $task->department->id, 'name' => $task->department->name, 'color' => $task->department->color, 'icon' => $task->department->icon] : null,
            'assigned_to'     => $task->assigned_to,
            'assignee'        => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name, 'email' => $task->assignee->email] : null,
            'created_by'      => $task->created_by,
            'creator'         => $task->creator ? ['id' => $task->creator->id, 'name' => $task->creator->name] : null,
            'priority'        => $task->priority,
            'status'          => $task->status,
            'due_date'        => $task->due_date?->format('Y-m-d'),
            'due_date_fmt'    => $task->due_date?->format('d M Y'),
            'is_overdue'      => $task->is_overdue,
            'requires_approval'=> $task->requires_approval,
            'is_recurring'    => $task->is_recurring,
            'recurrence_type' => $task->recurrence_type,
            'board_position'  => $task->board_position,
            'created_at'      => $task->created_at->format('d M Y, H:i'),
            'updated_at'      => $task->updated_at->format('d M Y, H:i'),
        ];

        if ($withDetails) {
            $data['comments']    = $task->comments ?? [];
            $data['attachments'] = $task->attachments ?? [];
        }

        return $data;
    }
}
