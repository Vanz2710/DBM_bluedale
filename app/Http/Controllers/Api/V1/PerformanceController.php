<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUp;
use App\Models\KpiTarget;
use App\Models\PerformanceTarget;
use App\Models\Project;
use App\Models\ToDo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    // ─── Existing task-targets (preserved) ────────────────────────────────────

    public function targets(string $userId)
    {
        $targets = PerformanceTarget::join('tasks', 'performance_targets.task_id', '=', 'tasks.id')
            ->select(
                'performance_targets.id',
                'performance_targets.user_id',
                'performance_targets.task_id',
                'performance_targets.weekly_target',
                'tasks.name as task_name',
            )
            ->where('performance_targets.user_id', $userId)
            ->orderBy('task_name')
            ->get();

        return response()->json(['data' => $targets]);
    }

    public function updateTargets(Request $request, string $userId)
    {
        $tasks         = $request->input('tasks', []);
        $weeklyTargets = $request->input('weekly_target', []);

        foreach ($tasks as $index => $task) {
            PerformanceTarget::updateOrCreate(
                ['user_id' => $userId, 'task_id' => $task['id']],
                ['weekly_target' => (int) ($weeklyTargets[$index] ?? 0)]
            );
        }

        return response()->json(['status' => 'success']);
    }

    public function report(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        $viewType  = $request->input('view', 'week');
        $userId    = $isAdmin
            ? ($request->input('user_id') ?: $authUser->id)
            : $authUser->id;
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $month     = $request->input('month');
        $year      = (int) $request->input('year', date('Y'));

        $query = ToDo::with('task')
            ->where('user_id', $userId)
            ->whereNotNull('task_id');

        if ($viewType === 'week' && $startDate && $endDate) {
            $query->whereBetween('todo_date', [$startDate, $endDate]);
        } elseif ($viewType === 'month' && $month) {
            $monthStart = $month . '-01';
            $monthEnd   = date('Y-m-t', strtotime($monthStart));
            $query->whereBetween('todo_date', [$monthStart, $monthEnd]);
        } else {
            $query->whereBetween('todo_date', ["{$year}-01-01", "{$year}-12-31"]);
        }

        $todos = $query->orderBy('todo_date')->get();

        if ($viewType === 'week') {
            $grouped = $todos->groupBy(fn($t) => $t->todo_date->format('Y-m-d'));
        } else {
            $grouped = $todos->groupBy(fn($t) => $t->todo_date->format('W'));
        }

        $result = $grouped->transform(function ($group) {
            return $group->groupBy('task.name')->transform(fn($g) => $g->count());
        });

        return response()->json(['data' => $result]);
    }

    // ─── KPI Overview ─────────────────────────────────────────────────────────

    public function overview(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        $userId = $isAdmin
            ? ($request->input('user_id') ?: $authUser->id)
            : $authUser->id;

        $view = $request->input('view', 'month');
        [$from, $to] = $this->periodDates($request, $view);
        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';
        $today  = now()->toDateString();

        $kpis = [
            'contacts_added'      => Contact::where('user_id', $userId)->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'todos_created'       => ToDo::where('user_id', $userId)->whereBetween('date_created', [$from, $to])->count(),
            'todos_due_today'     => ToDo::where('user_id', $userId)->where('todo_date', $today)->where('completion_status', '!=', 'completed')->count(),
            'todos_overdue'       => ToDo::where('user_id', $userId)->where('todo_date', '<', $today)->where('completion_status', 'pending')->count(),
            'todos_completed'     => ToDo::where('user_id', $userId)->where('completion_status', 'completed')->whereBetween('completed_at', [$fromDt, $toDt])->count(),
            'followups_created'   => FollowUp::whereHas('todo', fn($q) => $q->where('user_id', $userId))->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'followups_overdue'   => FollowUp::whereHas('todo', fn($q) => $q->where('user_id', $userId))->where('followup_date', '<', $today)->where('completion_status', 'pending')->count(),
            'followups_completed' => FollowUp::whereHas('todo', fn($q) => $q->where('user_id', $userId))->where('completion_status', 'completed')->whereBetween('completed_at', [$fromDt, $toDt])->count(),
            'projects_created'    => Project::where('user_id', $userId)->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'projects_open'       => Project::where('user_id', $userId)->where('project_enddate', '>=', $today)->count(),
            'deals_created'       => Deal::where('user_id', $userId)->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'deals_won'           => Deal::where('user_id', $userId)->where('status', 'won')->whereBetween('updated_at', [$fromDt, $toDt])->count(),
            'deals_lost'          => Deal::where('user_id', $userId)->where('status', 'lost')->whereBetween('updated_at', [$fromDt, $toDt])->count(),
            'won_deal_value'      => (float) Deal::where('user_id', $userId)->where('status', 'won')->whereBetween('updated_at', [$fromDt, $toDt])->sum('value'),
        ];

        // KPI targets vs actuals
        $kpiTargetRows = KpiTarget::where('user_id', $userId)->get()->keyBy('metric');
        $targets = [];
        foreach ($kpiTargetRows as $metric => $row) {
            $achieved     = (float) ($kpis[$metric] ?? 0);
            $target       = (float) $row->target_value;
            $targets[$metric] = [
                'target'   => $target,
                'achieved' => $achieved,
                'pct'      => $target > 0 ? min(100, (int) round($achieved / $target * 100)) : 0,
            ];
        }

        // Overdue todos (oldest first, limit 10)
        $overdueTodos = ToDo::with(['contact', 'task'])
            ->where('user_id', $userId)
            ->where('todo_date', '<', $today)
            ->where('completion_status', 'pending')
            ->orderBy('todo_date')
            ->limit(10)
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'todo_date'    => $t->todo_date?->format('Y-m-d'),
                'contact_id'   => $t->contact_id,
                'contact_name' => $t->contact?->name,
                'task'         => $t->task?->name,
                'days_overdue' => (int) now()->startOfDay()->diffInDays($t->todo_date, true),
            ]);

        // Overdue follow-ups (oldest first, limit 10)
        $overdueFollowups = FollowUp::with(['todo.contact', 'todo.task'])
            ->whereHas('todo', fn($q) => $q->where('user_id', $userId))
            ->where('followup_date', '<', $today)
            ->where('completion_status', 'pending')
            ->orderBy('followup_date')
            ->limit(10)
            ->get()
            ->map(fn($f) => [
                'id'            => $f->id,
                'followup_date' => $f->followup_date?->format('Y-m-d'),
                'contact_id'    => $f->todo?->contact_id,
                'contact_name'  => $f->todo?->contact?->name,
                'action_type'   => $f->action_type,
                'days_overdue'  => (int) now()->startOfDay()->diffInDays($f->followup_date, true),
            ]);

        // Overdue deals (past expected close date, still open)
        $overdueDeals = Deal::with('contact')
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '<', $today)
            ->orderBy('expected_close_date')
            ->limit(10)
            ->get()
            ->map(fn($d) => [
                'id'                  => $d->id,
                'title'               => $d->title,
                'stage'               => $d->stage,
                'expected_close_date' => $d->expected_close_date?->format('Y-m-d'),
                'contact_name'        => $d->contact?->name,
                'days_overdue'        => (int) now()->startOfDay()->diffInDays($d->expected_close_date, true),
            ]);

        return response()->json([
            'data' => [
                'period'            => ['from' => $from, 'to' => $to, 'view' => $view],
                'kpis'              => $kpis,
                'targets'           => $targets,
                'overdue_todos'     => $overdueTodos,
                'overdue_followups' => $overdueFollowups,
                'overdue_deals'     => $overdueDeals,
            ],
        ]);
    }

    // ─── Team comparison (admin only) ─────────────────────────────────────────

    public function team(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser->hasAnyRole(['admin', 'super-admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $view = $request->input('view', 'month');
        [$from, $to] = $this->periodDates($request, $view);
        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';
        $today  = now()->toDateString();

        $users    = User::orderBy('name')->get();
        $userIds  = $users->pluck('id')->all();

        // One query per metric instead of N queries per user
        $quotaTargets = KpiTarget::where('metric', 'won_deal_value')
            ->get()->keyBy('user_id');

        $contactsAdded = Contact::selectRaw('user_id, COUNT(*) as cnt')
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $todosCreated = ToDo::selectRaw('user_id, COUNT(*) as cnt')
            ->whereIn('user_id', $userIds)
            ->whereBetween('date_created', [$from, $to])
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $todosCompleted = ToDo::selectRaw('user_id, COUNT(*) as cnt')
            ->whereIn('user_id', $userIds)
            ->where('completion_status', 'completed')
            ->whereBetween('completed_at', [$fromDt, $toDt])
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $todosOverdue = ToDo::selectRaw('user_id, COUNT(*) as cnt')
            ->whereIn('user_id', $userIds)
            ->where('todo_date', '<', $today)
            ->where('completion_status', 'pending')
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $followupsCreated = DB::table('follow_ups')
            ->join('to_dos', 'follow_ups.todo_id', '=', 'to_dos.id')
            ->selectRaw('to_dos.user_id as uid, COUNT(*) as cnt')
            ->whereIn('to_dos.user_id', $userIds)
            ->whereBetween('follow_ups.created_at', [$fromDt, $toDt])
            ->groupBy('to_dos.user_id')->pluck('cnt', 'uid');

        $followupsCompleted = DB::table('follow_ups')
            ->join('to_dos', 'follow_ups.todo_id', '=', 'to_dos.id')
            ->selectRaw('to_dos.user_id as uid, COUNT(*) as cnt')
            ->whereIn('to_dos.user_id', $userIds)
            ->where('follow_ups.completion_status', 'completed')
            ->whereBetween('follow_ups.completed_at', [$fromDt, $toDt])
            ->groupBy('to_dos.user_id')->pluck('cnt', 'uid');

        $dealsWon = Deal::selectRaw('user_id, COUNT(*) as cnt, SUM(value) as total')
            ->whereIn('user_id', $userIds)
            ->where('status', 'won')
            ->whereBetween('updated_at', [$fromDt, $toDt])
            ->groupBy('user_id')
            ->get()->keyBy('user_id');

        $result = $users->map(function ($u) use (
            $contactsAdded, $todosCreated, $todosCompleted, $todosOverdue,
            $followupsCreated, $followupsCompleted, $dealsWon, $quotaTargets
        ) {
            $uid      = $u->id;
            $wonValue = (float) ($dealsWon[$uid]->total ?? 0);
            $quota    = isset($quotaTargets[$uid]) ? (float) $quotaTargets[$uid]->target_value : null;
            $quotaPct = ($quota && $quota > 0) ? min(100, (int) round($wonValue / $quota * 100)) : null;

            return [
                'user_id'              => $uid,
                'user_name'            => $u->name,
                'contacts_added'       => (int) ($contactsAdded[$uid]    ?? 0),
                'todos_created'        => (int) ($todosCreated[$uid]      ?? 0),
                'todos_completed'      => (int) ($todosCompleted[$uid]    ?? 0),
                'todos_overdue'        => (int) ($todosOverdue[$uid]      ?? 0),
                'followups_created'    => (int) ($followupsCreated[$uid]  ?? 0),
                'followups_completed'  => (int) ($followupsCompleted[$uid]?? 0),
                'deals_won'            => (int) ($dealsWon[$uid]->cnt     ?? 0),
                'won_deal_value'       => $wonValue,
                'revenue_quota'        => $quota,
                'quota_attainment_pct' => $quotaPct,
            ];
        });

        return response()->json([
            'data'   => $result,
            'period' => ['from' => $from, 'to' => $to, 'view' => $view],
        ]);
    }

    // ─── KPI Targets CRUD ─────────────────────────────────────────────────────

    public function kpiTargets(string $userId)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        if (!$isAdmin && $authUser->id != $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $rows = KpiTarget::where('user_id', $userId)->get()->keyBy('metric');

        return response()->json(['data' => $rows]);
    }

    public function updateKpiTargets(Request $request, string $userId)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasAnyRole(['admin', 'super-admin']);

        if (!$isAdmin && $authUser->id != $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'targets'              => 'required|array',
            'targets.*.metric'     => 'required|string|max:50',
            'targets.*.target_value' => 'required|numeric|min:0',
        ]);

        foreach ($validated['targets'] as $item) {
            KpiTarget::updateOrCreate(
                ['user_id' => $userId, 'metric' => $item['metric']],
                ['target_value' => $item['target_value']]
            );
        }

        return response()->json(['status' => 'success']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function periodDates(Request $request, string $view): array
    {
        if ($view === 'week') {
            $start = $request->input('start_date', now()->startOfWeek()->toDateString());
            $end   = $request->input('end_date',   now()->endOfWeek()->toDateString());
            return [$start, $end];
        }

        if ($view === 'month') {
            $month = $request->input('month', now()->format('Y-m'));
            [$y, $m] = explode('-', $month);
            return [
                date('Y-m-01', mktime(0, 0, 0, (int)$m, 1, (int)$y)),
                date('Y-m-t',  mktime(0, 0, 0, (int)$m, 1, (int)$y)),
            ];
        }

        if ($view === 'year') {
            $year = $request->input('year', date('Y'));
            return ["{$year}-01-01", "{$year}-12-31"];
        }

        // range fallback
        return [
            $request->input('from_date', now()->startOfMonth()->toDateString()),
            $request->input('to_date',   now()->toDateString()),
        ];
    }
}
