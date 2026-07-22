<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DeptNotification;
use App\Models\DeptTask;
use App\Models\DeptTaskComment;
use App\Models\User;
use App\Support\Csv;
use App\Support\XlsxExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\DeptTaskAttachment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeptTaskController extends Controller
{
    // "Admin-tier" for this module: sees/manages every task, not just their own.
    // Supervisor is included here — task deletion is the sole exception (see destroy()),
    // which stays restricted to admin/super-admin only.
    private function isAdmin(Request $request): bool
    {
        return $this->isElevatedUser($request->user());
    }

    private function isElevatedUser($user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin', 'supervisor']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin  = $this->isAdmin($request);

        // Non-admins are locked to their own stats only — ignore any user_id param,
        // same rule index()/weekly()/report() already enforce for this controller.
        $userId = $isAdmin
            ? ($request->filled('user_id') ? (int) $request->input('user_id') : null)
            : $authUser->id;

        $today = Carbon::today();

        // Single aggregated query instead of 7 separate COUNTs
        $statsRow = DeptTask::when($userId, fn($q) => $q->where('assigned_to', $userId))
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'pending') as pending,
                SUM(status = 'in_progress') as in_progress,
                SUM(status = 'completed') as completed,
                SUM(status = 'cancelled') as cancelled,
                SUM(status NOT IN ('completed','cancelled') AND due_date IS NOT NULL AND due_date < CURDATE()) as overdue
            ")
            ->first();

        $total      = (int) ($statsRow->total ?? 0);
        $pending    = (int) ($statsRow->pending ?? 0);
        $inProgress = (int) ($statsRow->in_progress ?? 0);
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
            'stats'        => compact('total', 'pending', 'inProgress', 'completed', 'cancelled', 'overdue'),
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
        // Anyone reaching this endpoint already holds 'manage dept-tasks' (enforced by the
        // route's can: middleware), so every such user needs the full roster for the
        // assignee/department pickers — not just admins.
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
        $isAdmin  = $this->isAdmin($request);

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

        $statusLabel = fn($s) => ['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'][$s] ?? $s;

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

        $filename = 'Task_Export_' . now()->format('Y-m-d') . '.' . ($format === 'csv' ? 'csv' : 'xlsx');

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

        $spreadsheet = XlsxExport::flatTable('Tasks', $selected, $LABELS, $WIDTHS, $q->lazy(300), $getVal);
        return XlsxExport::download($spreadsheet, $filename);
    }

    // Renders the Calendar tab's current month as an actual month-grid worksheet
    // (not a row list — CSV can't represent a grid, so this format is Excel-only).
    // Uses the same HTML-table-as-.xls trick as export() above for consistency.
    public function calendarExport(Request $request)
    {
        $authUser = $request->user();
        $isAdmin  = $this->isAdmin($request);

        $monthInput = (string) $request->input('month', '');
        $monthStart = preg_match('/^\d{4}-\d{2}$/', $monthInput)
            ? Carbon::createFromFormat('Y-m-d', $monthInput . '-01')->startOfMonth()
            : Carbon::today()->startOfMonth();

        // Same Monday-first, 6-week (42-day) grid the Calendar tab renders client-side.
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $gridStart->copy()->addDays(41);

        $showClosed = $request->boolean('show_closed');

        $q = DeptTask::with(['department', 'assignee:id,name'])
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);

        if (!$isAdmin) {
            $q->where('assigned_to', $authUser->id);
        } elseif ($request->filled('assigned_to')) {
            $q->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('department_id')) {
            $q->where('department_id', $request->department_id);
        }
        if (!$showClosed) {
            $q->whereNotIn('status', ['completed', 'cancelled']);
        }
        $q->orderByRaw("FIELD(priority, 'critical','high','medium','low')");

        $byDate = [];
        foreach ($q->get() as $t) {
            $byDate[$t->due_date->toDateString()][] = $t;
        }

        $scopeLabel = $isAdmin
            ? ($request->filled('assigned_to') ? 'Assignee: ' . (User::find($request->assigned_to)?->name ?? '—') : 'All Tasks')
            : 'My Tasks';
        if ($request->filled('department_id')) {
            $dept = Department::find($request->department_id);
            $scopeLabel .= $dept ? ' · ' . $dept->name : '';
        }

        $priorityColor = fn($p) => ['critical' => 'DC2626', 'high' => 'F97316', 'medium' => '3B82F6', 'low' => '64748B'][$p] ?? '64748B';
        $weekdays      = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $filename      = 'Task_Calendar_' . $monthStart->format('Y-m') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Calendar');
        foreach (range('A', 'G') as $letter) {
            $sheet->getColumnDimension($letter)->setWidth(24);
        }

        $sheet->mergeCells('A1:G1');
        XlsxExport::writeText($sheet, 'A1', 'Task Calendar — ' . $monthStart->format('F Y'));
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->mergeCells('A2:G2');
        XlsxExport::writeText($sheet, 'A2', $scopeLabel . ' · Generated ' . now()->format('d M Y, g:i A'));
        $sheet->getStyle('A2')->applyFromArray(['font' => ['size' => 9, 'color' => ['rgb' => '555555']]]);

        foreach ($weekdays as $i => $wd) {
            XlsxExport::writeText($sheet, Coordinate::stringFromColumnIndex($i + 1) . '3', $wd);
        }
        $sheet->getStyle('A3:G3')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('F3:G3')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']]]);

        $cursor   = $gridStart->copy();
        $todayIso = now()->toDateString();
        for ($week = 0; $week < 6; $week++) {
            $rowNum = 4 + $week;
            $sheet->getRowDimension($rowNum)->setRowHeight(80);
            for ($day = 0; $day < 7; $day++) {
                $coord   = Coordinate::stringFromColumnIndex($day + 1) . $rowNum;
                $iso     = $cursor->toDateString();
                $inMonth = $cursor->month === $monthStart->month;
                $isToday = $iso === $todayIso;
                $weekend = $day >= 5;

                // A single RichText cell so the day number and each task's priority
                // marker can carry their own colour — a plain string cell can only be one colour.
                $rich = new RichText();
                $dayRun = $rich->createTextRun((string) $cursor->day);
                $dayRun->getFont()->setBold(true)->setSize(10)->setColor(new Color($inMonth ? '000000' : '94A3B8'));

                foreach (($byDate[$iso] ?? []) as $t) {
                    $rich->createTextRun("\n");
                    $marker = $rich->createTextRun('■ ');
                    $marker->getFont()->setColor(new Color($priorityColor($t->priority)))->setSize(8);
                    $label = $rich->createTextRun($t->title . ($t->assignee ? ' (' . $t->assignee->name . ')' : ''));
                    $label->getFont()->setSize(8);
                }
                $sheet->setCellValue($coord, $rich);

                $bg = $isToday ? 'FEF3C7' : ($inMonth ? ($weekend ? 'F8FAFC' : 'FFFFFF') : 'F1F5F9');
                $sheet->getStyle($coord)->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                ]);

                $cursor->addDay();
            }
        }

        return XlsxExport::download($spreadsheet, $filename);
    }

    public function store(Request $request): JsonResponse
    {
        // Anyone with 'manage dept-tasks' may create tasks (enforced by the route's
        // can: middleware) — not just admins.
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'department_id'   => 'required|exists:departments,id',
            'assigned_to'     => 'nullable|exists:users,id',
            'priority'        => 'required|in:low,medium,high,critical',
            'status'          => 'nullable|in:pending,in_progress,completed,cancelled',
            'due_date'        => 'nullable|date',
            'is_important'    => 'boolean',
            'is_recurring'    => 'boolean',
            'recurrence_type' => 'nullable|in:daily,weekly,monthly,quarterly',
        ]);

        // Non-admins may only create tasks assigned to themselves (or unassigned) — matches
        // the read-only "Assigned To" field the New Task modal shows them. Admins can assign
        // to anyone with module access, checked below.
        if (!$this->isAdmin($request) && !empty($data['assigned_to']) && (int) $data['assigned_to'] !== $request->user()->id) {
            return response()->json(['message' => 'You can only create tasks assigned to yourself.'], 403);
        }

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
        if (!$this->isAdmin($request)
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

        $data = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string',
            'department_id'     => 'sometimes|exists:departments,id',
            'assigned_to'       => 'nullable|exists:users,id',
            'priority'          => 'sometimes|in:low,medium,high,critical',
            'status'            => 'sometimes|in:pending,in_progress,completed,cancelled',
            'due_date'          => 'nullable|date',
            'is_important'      => 'boolean',
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

        $this->maybeSpawnRecurrence($task, $oldStatus);

        return response()->json($this->formatTask($task));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Task deletion stays admin/super-admin only — deliberately narrower than isAdmin()
        // above, which now also covers supervisors for view/create/edit.
        if (!$request->user()->hasAnyRole(['admin', 'super-admin'])) {
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
            'status'         => 'required|in:pending,in_progress,completed,cancelled',
            'board_position' => 'nullable|integer',
        ]);

        $task  = DeptTask::findOrFail($id);
        $actor = $request->user();

        $this->assertCanTransition($task, $data['status'], $actor);

        $oldStatus = $task->status;
        $task->update($data);
        $task->load(['department', 'assignee:id,name,email', 'creator:id,name']);

        $this->maybeSpawnRecurrence($task, $oldStatus);

        // Notify assignee when someone else completes their task
        if ($task->status === 'completed' && $oldStatus !== 'completed'
            && $task->assigned_to && $task->assigned_to !== $actor->id) {
            DeptNotification::create([
                'user_id' => $task->assigned_to,
                'task_id' => $task->id,
                'type'    => 'completed',
                'message' => "Your task \"{$task->title}\" has been marked as completed.",
            ]);
        }

        return response()->json($this->formatTask($task));
    }

    public function addComment(Request $request, int $taskId): JsonResponse
    {
        $task = DeptTask::findOrFail($taskId);
        $user = $request->user();

        if (!$this->isAdmin($request)
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
        if (!$this->isAdmin($request)) {
            $query->where('user_id', $user->id);
        }
        $query->firstOrFail()->delete();
        return response()->json(['ok' => true]);
    }

    public function weekly(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin  = $this->isAdmin($request);

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
                'id'             => $t->id,
                'title'          => $t->title,
                'due_date'       => $t->due_date?->format('d/m'),
                'due_date_sort'  => $t->due_date?->format('Y-m-d'),
                'status'         => $t->status,
                'priority'       => $t->priority,
                'assignee'       => $t->assignee?->name,
                'is_overdue'     => $t->is_overdue,
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
        $isAdmin  = $this->isAdmin($request);

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

        if (!$this->isAdmin($request)
            && $task->assigned_to !== $user->id
            && $task->created_by  !== $user->id) {
            abort(403, 'You can only attach files to tasks you are involved in.');
        }

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,docm,xls,xlsx,xlsm,ppt,pptx,pptm,txt,csv,jpg,jpeg,png,gif,webp,zip',
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
        if (!$this->isAdmin($request)) {
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
     */
    private function assertCanTransition(DeptTask $task, string $to, $user): void
    {
        $from = $task->status;
        if ($from === $to) {
            return;
        }

        $isAdmin    = $this->isElevatedUser($user);
        $isAssignee = $task->assigned_to === $user->id;
        $isCreator  = $task->created_by  === $user->id;

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
            'is_important'    => $task->is_important,
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
