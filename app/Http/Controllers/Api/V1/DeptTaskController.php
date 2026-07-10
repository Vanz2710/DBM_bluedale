<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DeptNotification;
use App\Models\DeptTask;
use App\Models\DeptTaskComment;
use App\Models\User;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\DeptTaskAttachment;

class DeptTaskController extends Controller
{
    private function isAdmin(Request $request): bool
    {
        return $request->user()->hasAnyRole(['admin', 'super-admin']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $userId   = null;

        if ($request->filled('user_id')) {
            $requestedId = (int) $request->input('user_id');
            if ($requestedId === $authUser->id || $authUser->hasAnyRole(['admin', 'super-admin'])) {
                $userId = $requestedId;
            }
        }

        $today = Carbon::today();

        // Single aggregated query instead of 7 separate COUNTs
        $statsRow = DeptTask::when($userId, fn($q) => $q->where('assigned_to', $userId))
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'pending') as pending,
                SUM(status = 'in_progress') as in_progress,
                SUM(status = 'waiting_approval') as waiting,
                SUM(status = 'completed') as completed,
                SUM(status = 'cancelled') as cancelled,
                SUM(status NOT IN ('completed','cancelled') AND due_date IS NOT NULL AND due_date < CURDATE()) as overdue
            ")
            ->first();

        $total      = (int) ($statsRow->total ?? 0);
        $pending    = (int) ($statsRow->pending ?? 0);
        $inProgress = (int) ($statsRow->in_progress ?? 0);
        $waiting    = (int) ($statsRow->waiting ?? 0);
        $completed  = (int) ($statsRow->completed ?? 0);
        $cancelled  = (int) ($statsRow->cancelled ?? 0);
        $overdue    = (int) ($statsRow->overdue ?? 0);

        // Active-only count so the dept bar chart reflects current workload, not all-time history.
        // Zero-task departments are filtered out to avoid flat bars cluttering the chart.
        $byDepartment = Department::withCount([
            'tasks as tasks_count'     => fn($q) => $q->when($userId, fn($q2) => $q2->where('assigned_to', $userId))
                                                      ->whereNotIn('status', ['completed', 'cancelled']),
            'tasks as pending_count'   => fn($q) => $q->when($userId, fn($q2) => $q2->where('assigned_to', $userId))->where('status', 'pending'),
            'tasks as completed_count' => fn($q) => $q->when($userId, fn($q2) => $q2->where('assigned_to', $userId))->where('status', 'completed'),
        ])->get()
          ->map(fn($d) => [
              'name'      => $d->name,
              'color'     => $d->color,
              'total'     => $d->tasks_count,
              'pending'   => $d->pending_count,
              'completed' => $d->completed_count,
          ])
          ->filter(fn($d) => $d['total'] > 0)
          ->values();

        $byStatus = [
            ['label' => 'Pending',          'value' => $pending,    'color' => '#94a3b8'],
            ['label' => 'In Progress',       'value' => $inProgress, 'color' => '#3b82f6'],
            ['label' => 'Waiting Approval',  'value' => $waiting,    'color' => '#f59e0b'],
            ['label' => 'Completed',         'value' => $completed,  'color' => '#10b981'],
            ['label' => 'Cancelled',         'value' => $cancelled,  'color' => '#6b7280'],
        ];

        // Two queries instead of 12 (6 weeks × 2): aggregate by ISO week then map in PHP
        $sixWeeksStart = $today->copy()->startOfWeek()->subWeeks(5)->startOfDay();

        $createdByWeek = DeptTask::when($userId, fn($q) => $q->where('assigned_to', $userId))
            ->where('created_at', '>=', $sixWeeksStart)
            ->selectRaw("DATE_FORMAT(created_at, '%x%v') as yw, COUNT(*) as cnt")
            ->groupBy('yw')
            ->pluck('cnt', 'yw');

        $completedByWeek = DeptTask::when($userId, fn($q) => $q->where('assigned_to', $userId))
            ->where('status', 'completed')
            ->where('updated_at', '>=', $sixWeeksStart)
            ->selectRaw("DATE_FORMAT(updated_at, '%x%v') as yw, COUNT(*) as cnt")
            ->groupBy('yw')
            ->pluck('cnt', 'yw');

        $weeklyRate = [];
        for ($i = 5; $i >= 0; $i--) {
            $weekStart = $today->copy()->startOfWeek()->subWeeks($i);
            $yw = $weekStart->format('oW'); // ISO year + zero-padded week — matches DATE_FORMAT %x%v
            $weeklyRate[] = [
                'week'      => 'W' . $weekStart->weekOfYear,
                'label'     => $weekStart->format('d M'),
                'completed' => (int) ($completedByWeek[$yw] ?? 0),
                'created'   => (int) ($createdByWeek[$yw] ?? 0),
            ];
        }

        $recentTasks = DeptTask::with(['department', 'assignee', 'creator'])
            ->when($userId, fn($q) => $q->where('assigned_to', $userId))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest('updated_at')->limit(8)->get()
            ->map(fn($t) => $this->formatTask($t));

        return response()->json([
            'stats'        => compact('total', 'pending', 'inProgress', 'waiting', 'completed', 'cancelled', 'overdue'),
            'byDepartment' => $byDepartment,
            'byStatus'     => $byStatus,
            'weeklyRate'   => $weeklyRate,
            'recentTasks'  => $recentTasks,
            'scoped_user'  => $userId,
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json(Department::withCount('tasks')->orderBy('name')->get());
    }

    public function users(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            abort(403);
        }
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
        $query = DeptNotification::where('user_id', $request->user()->id)->whereNull('read_at');
        if ($request->filled('id')) {
            $query->where('id', (int) $request->input('id'));
        }
        $query->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        $q = DeptTask::with(['department', 'assignee:id,name,email', 'creator:id,name']);

        // Non-admins are locked to their own tasks only — ignore any assigned_to param
        if (!$isAdmin) {
            $q->where('assigned_to', $authUser->id);
        } elseif ($request->filled('assigned_to')) {
            $q->where('assigned_to', $request->assigned_to);
        }

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

        // Board view requests all tasks without pagination
        if ($request->boolean('all')) {
            $tasks = $q->orderBy($sortBy, $sortDir)->get()->map(fn($t) => $this->formatTask($t));
            return response()->json($tasks);
        }

        $perPage   = min((int) $request->input('per_page', 20), 100);
        $paginated = $q->orderBy($sortBy, $sortDir)->paginate($perPage, ['*'], 'page', $request->input('page', 1));
        $paginated->through(fn($t) => $this->formatTask($t));

        return response()->json($paginated);
    }

    public function export(Request $request)
    {
        $authUser = $request->user();
        $isAdmin  = $this->isAdmin($request);

        $ALLOWED = ['no', 'title', 'department', 'assigned_to', 'priority', 'status', 'due_date', 'created_by', 'created_at', 'description'];
        $LABELS  = [
            'no' => 'No', 'title' => 'Task', 'department' => 'Department',
            'assigned_to' => 'Assigned To', 'priority' => 'Priority', 'status' => 'Status',
            'due_date' => 'Due Date', 'created_by' => 'Created By', 'created_at' => 'Created On',
            'description' => 'Description',
        ];
        $WIDTHS = [
            'no' => 28, 'title' => 220, 'department' => 130, 'assigned_to' => 130,
            'priority' => 70, 'status' => 100, 'due_date' => 75, 'created_by' => 130,
            'created_at' => 75, 'description' => 260,
        ];

        $requested = array_filter(explode(',', $request->input('cols', '')));
        $selected  = !empty($requested) ? array_values(array_intersect($ALLOWED, $requested)) : $ALLOWED;
        $format    = $request->input('format') === 'csv' ? 'csv' : 'xls';

        // Same filters as index() — export reflects whatever the Table tab is currently showing.
        $q = DeptTask::with(['department', 'assignee:id,name,email', 'creator:id,name']);

        if (!$isAdmin) {
            $q->where('assigned_to', $authUser->id);
        } elseif ($request->filled('assigned_to')) {
            $q->where('assigned_to', $request->assigned_to);
        }
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
        $q->orderBy($sortBy, $sortDir);

        $statusLabel = fn($s) => ['pending' => 'Pending', 'in_progress' => 'In Progress', 'waiting_approval' => 'Waiting Approval', 'completed' => 'Completed', 'cancelled' => 'Cancelled'][$s] ?? $s;

        $getVal = fn($t, $col) => match ($col) {
            'no'          => null,
            'title'       => $t->title,
            'department'  => $t->department?->name ?? '',
            'assigned_to' => $t->assignee?->name ?? '',
            'priority'    => ucfirst($t->priority),
            'status'      => $t->is_overdue ? 'Overdue' : $statusLabel($t->status),
            'due_date'    => $t->due_date?->format('d M Y') ?? '',
            'created_by'  => $t->creator?->name ?? '',
            'created_at'  => $t->created_at?->format('d M Y') ?? '',
            'description' => $t->description ?? '',
            default       => '',
        };

        $filename = 'Task_Export_' . now()->format('Y-m-d') . '.' . $format;

        if ($format === 'csv') {
            return response()->stream(function () use ($q, $selected, $LABELS, $getVal) {
                $h = fopen('php://output', 'w');
                fwrite($h, "\xEF\xBB\xBF");
                fputcsv($h, array_map(fn($k) => $LABELS[$k] ?? $k, $selected));
                $no = 1;
                $q->chunk(300, function ($tasks) use ($h, $selected, $getVal, &$no) {
                    foreach ($tasks as $t) {
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

        return response()->stream(function () use ($q, $selected, $LABELS, $WIDTHS, $getVal) {
            $esc     = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $thStyle = 'font-family:Arial,sans-serif;font-size:10pt;font-weight:bold;color:#000000;background:#ffffff;border:1pt solid #000000;padding:6pt 9pt;white-space:nowrap;text-align:left;';
            $tdStyle = 'font-family:Arial,sans-serif;font-size:10pt;color:#000000;border:1pt solid #000000;padding:5pt 9pt;vertical-align:top;';
            echo "\xEF\xBB\xBF";
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Tasks</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
            echo '<body><table style="border-collapse:collapse;"><colgroup>';
            foreach ($selected as $col) { echo '<col style="width:' . ($WIDTHS[$col] ?? 100) . 'pt">'; }
            echo '</colgroup><thead><tr>';
            foreach ($selected as $col) { echo '<th style="' . $thStyle . '">' . $esc($LABELS[$col] ?? $col) . '</th>'; }
            echo '</tr></thead><tbody>';
            $no = 1;
            $q->chunk(300, function ($tasks) use (&$no, $selected, $esc, $tdStyle, $getVal) {
                foreach ($tasks as $t) {
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

    public function store(Request $request): JsonResponse
    {
        // Task creation is admin-only for now. To let users create their own tasks,
        // remove this guard and the matching v-if="isAdmin" on the New Task buttons.
        if (!$this->isAdmin($request)) {
            abort(403, 'Only admins can create tasks.');
        }

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

        if (!empty($data['assigned_to'])) {
            $assignee = User::findOrFail($data['assigned_to']);
            if (!$assignee->can('manage dept-tasks')) {
                return response()->json(['message' => 'The assigned user does not have Task Manager access.'], 422);
            }
        }

        $data['created_by']     = $request->user()->id;
        $data['status']         = $data['status'] ?? 'pending';
        $data['board_position'] = DeptTask::where('department_id', $data['department_id'])
            ->where('status', $data['status'])
            ->count();

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

    public function show(Request $request, int $id): JsonResponse
    {
        $task = DeptTask::with([
            'department',
            'assignee:id,name,email',
            'creator:id,name',
            'comments.user:id,name',
            'attachments.user:id,name',
        ])->findOrFail($id);

        $user = $request->user();
        if (!$user->hasAnyRole(['admin', 'super-admin'])
            && $task->assigned_to !== $user->id
            && $task->created_by  !== $user->id) {
            abort(403);
        }

        return response()->json($this->formatTask($task, true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = DeptTask::findOrFail($id);

        // Only admins or the task's creator may edit task details
        if (!$this->isAdmin($request) && $task->created_by !== $request->user()->id) {
            abort(403, 'You are not allowed to edit this task.');
        }

        // Freeze edits while awaiting approval — only admins may still change details
        if ($task->status === 'waiting_approval' && !$this->isAdmin($request)) {
            abort(403, 'This task is pending approval and cannot be edited.');
        }

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

        // A status change via the edit form must obey the same workflow rules
        if (array_key_exists('status', $data) && $data['status'] !== $task->status) {
            $this->assertCanTransition($task, $data['status'], $request->user());
        }

        if (!empty($data['assigned_to']) && $data['assigned_to'] !== $task->assigned_to) {
            $assignee = User::findOrFail($data['assigned_to']);
            if (!$assignee->can('manage dept-tasks')) {
                return response()->json(['message' => 'The assigned user does not have Task Manager access.'], 422);
            }
        }

        $oldAssignee = $task->assigned_to;
        $oldStatus   = $task->status;
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

        $this->maybeNotifyApproval($task, $oldStatus, $request->user());
        $this->maybeSpawnRecurrence($task, $oldStatus);

        return response()->json($this->formatTask($task));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Only admins may delete tasks
        if (!$this->isAdmin($request)) {
            abort(403, 'Only admins can delete tasks.');
        }

        // The attachments table cascade-deletes at the DB level, but the physical
        // files on disk don't clean themselves up — remove them explicitly first.
        $task = DeptTask::with('attachments')->findOrFail($id);
        foreach ($task->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }
        $task->delete();

        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status'         => 'required|in:pending,in_progress,waiting_approval,completed,cancelled',
            'board_position' => 'nullable|integer',
        ]);

        $task  = DeptTask::findOrFail($id);
        $actor = $request->user();

        $this->assertCanTransition($task, $data['status'], $actor);

        $oldStatus = $task->status;
        $task->update($data);
        $task->load(['department', 'assignee:id,name,email', 'creator:id,name']);

        $this->maybeNotifyApproval($task, $oldStatus, $actor);
        $this->maybeSpawnRecurrence($task, $oldStatus);

        // Approver sent the task back for changes (waiting_approval → in_progress)
        if ($oldStatus === 'waiting_approval' && $task->status === 'in_progress'
            && $task->assigned_to && $task->assigned_to !== $actor->id) {
            DeptNotification::create([
                'user_id' => $task->assigned_to,
                'task_id' => $task->id,
                'type'    => 'rejected',
                'message' => "{$actor->name} requested changes on \"{$task->title}\".",
            ]);
        }

        // Notify assignee when someone else completes (or approves) their task
        if ($task->status === 'completed' && $oldStatus !== 'completed'
            && $task->assigned_to && $task->assigned_to !== $actor->id) {
            DeptNotification::create([
                'user_id' => $task->assigned_to,
                'task_id' => $task->id,
                'type'    => 'completed',
                'message' => $oldStatus === 'waiting_approval'
                    ? "Your task \"{$task->title}\" was approved and marked complete."
                    : "Your task \"{$task->title}\" has been marked as completed.",
            ]);
        }

        return response()->json($this->formatTask($task));
    }

    public function addComment(Request $request, int $taskId): JsonResponse
    {
        $task = DeptTask::findOrFail($taskId);
        $user = $request->user();

        if (!$user->hasAnyRole(['admin', 'super-admin'])
            && $task->assigned_to !== $user->id
            && $task->created_by  !== $user->id) {
            abort(403, 'You can only comment on tasks you are involved in.');
        }

        $data    = $request->validate(['comment' => 'required|string']);
        $comment = DeptTaskComment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'comment' => $data['comment'],
        ]);
        $comment->load('user:id,name');

        return response()->json($comment, 201);
    }

    public function deleteComment(Request $request, int $taskId, int $commentId): JsonResponse
    {
        $user  = $request->user();
        $query = DeptTaskComment::where('task_id', $taskId)->where('id', $commentId);
        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            $query->where('user_id', $user->id);
        }
        $query->firstOrFail()->delete();
        return response()->json(['ok' => true]);
    }

    public function weekly(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->week_start)->startOfDay()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $departments = Department::orderBy('id')->get();

        // Single query for all tasks, grouped by department in PHP (no N+1)
        $allWeeklyTasks = DeptTask::whereIn('department_id', $departments->pluck('id'))
            ->when(!$isAdmin, fn($q) => $q->where('assigned_to', $authUser->id))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                  ->orWhere('due_date', '<', $weekStart->toDateString());
            })
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get()
            ->groupBy('department_id');

        $tasksByDept = [];
        foreach ($departments as $dept) {
            $tasks = ($allWeeklyTasks[$dept->id] ?? collect())->map(fn($t) => [
                'id'         => $t->id,
                'title'      => $t->title,
                'due_date'   => $t->due_date?->format('d/m'),
                'status'     => $t->status,
                'priority'   => $t->priority,
                'assignee'   => $t->assignee?->name,
                'is_overdue' => $t->is_overdue,
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
        $authUser = $request->user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
        $dateTo   = $request->filled('date_to')   ? Carbon::parse($request->date_to)   : Carbon::now();

        $tasks = DeptTask::with(['department', 'assignee:id,name', 'creator:id,name'])
            ->when(!$isAdmin, fn($q) => $q->where('assigned_to', $authUser->id))
            ->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
                  ->orWhere(fn($inner) => $inner->where('status', 'completed')
                      ->whereBetween('updated_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()]));
            })
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

    public function storeAttachment(Request $request, int $taskId): JsonResponse
    {
        $task = DeptTask::findOrFail($taskId);
        $user = $request->user();

        if (!$user->hasAnyRole(['admin', 'super-admin'])
            && $task->assigned_to !== $user->id
            && $task->created_by  !== $user->id) {
            abort(403, 'You can only attach files to tasks you are involved in.');
        }

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,webp,zip',
        ]);

        $file = $request->file('file');
        $path = $file->store("dept-attachments/{$taskId}", 'public');

        $attachment = DeptTaskAttachment::create([
            'task_id'   => $taskId,
            'user_id'   => $request->user()->id,
            'filename'  => $file->getClientOriginalName(),
            'path'      => $path,
            'size'      => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
        $attachment->load('user:id,name');

        return response()->json([
            'id'        => $attachment->id,
            'filename'  => $attachment->filename,
            'size'      => $attachment->size,
            'mime_type' => $attachment->mime_type,
            'url'       => Storage::url($path),
            'user'      => $attachment->user ? ['id' => $attachment->user->id, 'name' => $attachment->user->name] : null,
            'created_at'=> $attachment->created_at->format('d M Y, H:i'),
        ], 201);
    }

    public function deleteAttachment(Request $request, int $taskId, int $attachmentId): JsonResponse
    {
        $user  = $request->user();
        $query = DeptTaskAttachment::where('task_id', $taskId)->where('id', $attachmentId);
        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            $query->where('user_id', $user->id);
        }
        $attachment = $query->firstOrFail();
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(['ok' => true]);
    }

    public function listAttachments(Request $request): JsonResponse
    {
        $user    = $request->user();
        $isAdmin = $this->isAdmin($request);

        $query = DeptTaskAttachment::with([
            'user:id,name',
            'task:id,title,department_id',
            'task.department:id,name,color',
        ]);

        if (!$isAdmin) {
            $query->whereHas('task', function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by',  $user->id);
            });
        }

        if ($request->filled('search')) {
            $query->where('filename', 'like', '%' . $request->search . '%');
        }

        $rows = $query->orderByDesc('created_at')->paginate(30);

        return response()->json($rows->through(fn($a) => [
            'id'        => $a->id,
            'filename'  => $a->filename,
            'size'      => $a->size,
            'mime_type' => $a->mime_type,
            'url'       => Storage::url($a->path),
            'created_at'=> $a->created_at->format('d M Y, H:i'),
            'user'      => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            'task'      => $a->task ? [
                'id'         => $a->task->id,
                'title'      => $a->task->title,
                'department' => $a->task->department
                    ? ['name' => $a->task->department->name, 'color' => $a->task->department->color]
                    : null,
            ] : null,
        ]));
    }

    public function renameAttachment(Request $request, int $attachmentId): JsonResponse
    {
        $user  = $request->user();
        $query = DeptTaskAttachment::where('id', $attachmentId);
        if (!$this->isAdmin($request)) {
            $query->where('user_id', $user->id);
        }
        $attachment = $query->firstOrFail();
        $request->validate(['filename' => 'required|string|max:255']);
        $attachment->update(['filename' => trim($request->filename)]);
        return response()->json(['ok' => true, 'filename' => $attachment->filename]);
    }

    public function deleteAttachmentDirect(Request $request, int $attachmentId): JsonResponse
    {
        $user  = $request->user();
        $query = DeptTaskAttachment::where('id', $attachmentId);
        if (!$this->isAdmin($request)) {
            $query->where('user_id', $user->id);
        }
        $attachment = $query->firstOrFail();
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Enforce the task workflow state machine.
     *
     * Approver = an admin who is NOT the task's assignee (no self-approval).
     * Assignee  = the person doing the work.
     *
     * Approval gate: requires_approval tasks must pass through waiting_approval
     * before reaching completed, and only an approver (non-assignee admin) can
     * make that final step. Even admins who are also the assignee must submit
     * and have another admin approve.
     */
    private function assertCanTransition(DeptTask $task, string $to, $user): void
    {
        $from = $task->status;
        if ($from === $to) {
            return;
        }

        $isAdmin    = $user->hasAnyRole(['admin', 'super-admin']);
        $isAssignee = $task->assigned_to === $user->id;
        $isCreator  = $task->created_by  === $user->id;
        // Approver = admin who is not doing the work themselves
        $isApprover = $isAdmin && !$isAssignee;

        // Must be involved with the task.
        if (!$isAdmin && !$isAssignee && !$isCreator) {
            abort(403, 'You can only update your own tasks.');
        }

        // Cancelling is admin-only.
        if ($to === 'cancelled' && !$isAdmin) {
            abort(403, 'Only admins can cancel tasks.');
        }

        // Reopening a finished task is admin-only.
        if (in_array($from, ['completed', 'cancelled'], true) && !$isAdmin) {
            abort(403, 'Only an admin can reopen a finished task.');
        }

        // Only the assignee (to withdraw) or an approver (to approve/reject) may act on a submitted task.
        if ($from === 'waiting_approval' && !$isApprover && !$isAssignee) {
            abort(403, 'Only the assignee or an approver can act on a submitted task.');
        }

        // Sequential guard: completed must come from waiting_approval for approval tasks.
        // This blocks pending→completed and in_progress→completed shortcuts for everyone.
        if ($to === 'completed' && $task->requires_approval && $from !== 'waiting_approval') {
            abort(422, 'This task must be submitted for approval before it can be marked complete.');
        }

        // Approval gate: only a non-assignee admin can move a submitted task to completed.
        if ($to === 'completed' && $task->requires_approval && !$isApprover) {
            abort(403, 'This task requires admin approval. You cannot approve your own work.');
        }
    }

    private function maybeNotifyApproval(DeptTask $task, string $oldStatus, $actor): void
    {
        if ($task->status !== 'waiting_approval' || $oldStatus === 'waiting_approval') {
            return;
        }

        // Notify all admins who are not the submitter — they are the potential approvers.
        $admins = User::role(['admin', 'super-admin'])->where('id', '!=', $actor->id)->pluck('id');
        foreach ($admins as $adminId) {
            DeptNotification::create([
                'user_id' => $adminId,
                'task_id' => $task->id,
                'type'    => 'approval_needed',
                'message' => "{$actor->name} submitted \"{$task->title}\" for approval.",
            ]);
        }
    }

    /**
     * When a recurring task is completed, spawn its next occurrence.
     * Event-driven (fires on completion) rather than a scheduled scan, so a task
     * with no due date simply recurs from today instead of never generating.
     */
    private function maybeSpawnRecurrence(DeptTask $task, string $oldStatus): void
    {
        if ($task->status !== 'completed' || $oldStatus === 'completed') {
            return;
        }
        if (!$task->is_recurring || !$task->recurrence_type) {
            return;
        }

        $base = $task->due_date ?? Carbon::today();
        $nextDue = match ($task->recurrence_type) {
            'daily'     => $base->copy()->addDay(),
            'weekly'    => $base->copy()->addWeek(),
            'monthly'   => $base->copy()->addMonth(),
            'quarterly' => $base->copy()->addMonths(3),
            default     => $base->copy()->addWeek(),
        };

        $next = DeptTask::create([
            'title'             => $task->title,
            'description'       => $task->description,
            'department_id'     => $task->department_id,
            'assigned_to'       => $task->assigned_to,
            'created_by'        => $task->created_by,
            'priority'          => $task->priority,
            'status'            => 'pending',
            'due_date'          => $nextDue,
            'requires_approval' => $task->requires_approval,
            'is_recurring'      => true,
            'recurrence_type'   => $task->recurrence_type,
            'board_position'    => DeptTask::where('department_id', $task->department_id)->where('status', 'pending')->count(),
        ]);

        // Breadcrumb on the just-completed task so its history shows when the next one was scheduled.
        $task->update(['next_recurrence_date' => $nextDue]);

        if ($next->assigned_to) {
            DeptNotification::create([
                'user_id' => $next->assigned_to,
                'task_id' => $next->id,
                'type'    => 'assigned',
                'message' => "Recurring task created: {$next->title}",
            ]);
        }
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
            'next_recurrence_date' => $task->next_recurrence_date?->format('d M Y'),
            'board_position'  => $task->board_position,
            'created_at'      => $task->created_at->format('d M Y, H:i'),
            'updated_at'      => $task->updated_at->format('d M Y, H:i'),
        ];

        if ($withDetails) {
            $data['comments']    = $task->comments ?? [];
            $data['attachments'] = ($task->attachments ?? collect())->map(fn($a) => [
                'id'        => $a->id,
                'filename'  => $a->filename,
                'size'      => $a->size,
                'mime_type' => $a->mime_type,
                'url'       => Storage::url($a->path),
                'user'      => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                'created_at'=> $a->created_at?->format('d M Y, H:i'),
            ])->values();
        }

        return $data;
    }
}
