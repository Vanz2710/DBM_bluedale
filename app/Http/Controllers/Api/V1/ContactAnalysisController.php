<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\ToDo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactAnalysisController extends Controller
{
    private function dates(Request $request): array
    {
        $to   = $request->get('to',   Carbon::today()->toDateString());
        $from = $request->get('from', Carbon::today()->subDays(29)->toDateString());
        return [$from, $to];
    }

    private function userId(Request $request): ?int
    {
        $u = $request->user();
        if ($u->hasAnyRole(['admin', 'super-admin'])) {
            return $request->filled('user_id') ? (int) $request->user_id : null;
        }
        return $u->id;
    }

    // ── Overview: KPI cards + daily trend ────────────────────────────────────
    public function overview(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $uid = $this->userId($request);

        $fromC = Carbon::parse($from);
        $toC   = Carbon::parse($to);
        $days  = $fromC->diffInDays($toC) + 1;
        $prevTo   = $fromC->copy()->subDay()->toDateString();
        $prevFrom = $fromC->copy()->subDays($days)->toDateString();

        $cBase = fn () => Contact::when($uid, fn ($q) => $q->where('user_id', $uid));
        $tBase = fn () => ToDo::when($uid,    fn ($q) => $q->where('user_id', $uid));
        $fBase = fn () => FollowUp::when($uid, fn ($q) =>
            $q->whereHas('todo', fn ($q2) => $q2->where('user_id', $uid))
        );

        // Current period
        $c1 = $cBase()->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])->count();
        $t1 = $tBase()->whereBetween('todo_date',  [$from, $to])->count();
        $f1 = $fBase()->where('completion_status', 'completed')
                ->whereBetween('completed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])->count();
        $e1 = $tBase()->whereBetween('todo_date', [$from, $to])->distinct('contact_id')->count('contact_id');

        // Previous period (same length)
        $c0 = $cBase()->whereBetween('created_at', ["{$prevFrom} 00:00:00", "{$prevTo} 23:59:59"])->count();
        $t0 = $tBase()->whereBetween('todo_date',  [$prevFrom, $prevTo])->count();
        $f0 = $fBase()->where('completion_status', 'completed')
                ->whereBetween('completed_at', ["{$prevFrom} 00:00:00", "{$prevTo} 23:59:59"])->count();
        $e0 = $tBase()->whereBetween('todo_date', [$prevFrom, $prevTo])->distinct('contact_id')->count('contact_id');

        // Daily trend (fill gaps with 0)
        $cDaily = $cBase()->selectRaw('DATE(created_at) as d, count(*) as cnt')
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->groupBy('d')->pluck('cnt', 'd');

        $tDaily = $tBase()->selectRaw('todo_date as d, count(*) as cnt')
            ->whereBetween('todo_date', [$from, $to])
            ->groupBy('d')->pluck('cnt', 'd');

        $fDaily = $fBase()->selectRaw('DATE(followup_date) as d, count(*) as cnt')
            ->whereBetween('followup_date', [$from, $to])
            ->groupBy('d')->pluck('cnt', 'd');

        $trend = [];
        $cur = $fromC->copy();
        while ($cur->lte($toC)) {
            $d = $cur->toDateString();
            $trend[] = [
                'date'      => $d,
                'contacts'  => (int) ($cDaily[$d] ?? 0),
                'tasks'     => (int) ($tDaily[$d] ?? 0),
                'followups' => (int) ($fDaily[$d] ?? 0),
            ];
            $cur->addDay();
        }

        return response()->json([
            'period'                   => ['from' => $from, 'to' => $to, 'days' => $days],
            'contacts_added'           => $c1,
            'tasks_created'            => $t1,
            'followups_completed'      => $f1,
            'engaged_contacts'         => $e1,
            'prev_contacts_added'      => $c0,
            'prev_tasks_created'       => $t0,
            'prev_followups_completed' => $f0,
            'prev_engaged_contacts'    => $e0,
            'daily_trend'              => $trend,
        ]);
    }

    // ── Lead Source breakdown ─────────────────────────────────────────────────
    public function leadSource(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $uid = $this->userId($request);

        $base = Contact::whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->when($uid,                             fn ($q) => $q->where('user_id', $uid))
            ->when($request->filled('status_id'),    fn ($q) => $q->where('status_id', $request->status_id))
            ->when($request->filled('industry_id'),  fn ($q) => $q->where('industry_id', $request->industry_id));

        $total   = (clone $base)->count();
        $sources = (clone $base)
            ->selectRaw("COALESCE(NULLIF(lead_source,''), 'unknown') as source, count(*) as cnt")
            ->groupBy('source')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => [
                'source' => $r->source,
                'label'  => ucwords(str_replace('_', ' ', $r->source)),
                'count'  => (int) $r->cnt,
                'pct'    => $total > 0 ? round($r->cnt / $total * 100, 1) : 0,
            ]);

        return response()->json(['total' => $total, 'sources' => $sources]);
    }

    // ── Follow-up action types ────────────────────────────────────────────────
    public function followupActions(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $uid = $this->userId($request);

        $base = FollowUp::whereBetween('followup_date', [$from, $to])
            ->when($uid, fn ($q) =>
                $q->whereHas('todo', fn ($q2) => $q2->where('user_id', $uid))
            )
            ->when($request->filled('status_id'), fn ($q) =>
                $q->whereHas('todo.contact', fn ($q2) => $q2->where('status_id', $request->status_id))
            );

        $total    = (clone $base)->count();
        $byAction = (clone $base)
            ->selectRaw("
                COALESCE(NULLIF(action_type,''), 'Other') as action_type,
                COUNT(*) as total,
                SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN completion_status = 'pending'   THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN completion_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->groupBy('action_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'action_type'     => $r->action_type,
                'label'           => $r->action_type,
                'total'           => (int) $r->total,
                'completed'       => (int) $r->completed,
                'pending'         => (int) $r->pending,
                'cancelled'       => (int) $r->cancelled,
                'completion_rate' => $r->total > 0 ? round($r->completed / $r->total * 100, 1) : 0,
                'pct'             => $total > 0 ? round($r->total / $total * 100, 1) : 0,
            ]);

        return response()->json(['total' => $total, 'by_action' => $byAction]);
    }

    // ── Engagement health table ───────────────────────────────────────────────
    public function engagement(Request $request)
    {
        $uid     = $this->userId($request);
        $health  = $request->get('health', '');
        $perPage = min((int) $request->get('per_page', 50), 200);
        $search  = $request->get('q', '');
        $sortBy  = in_array($request->get('sort_by'), ['name', 'days_inactive', 'last_todo_date'])
                   ? $request->get('sort_by') : 'days_inactive';
        $sortDir = $request->get('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $latestTodo = ToDo::select('contact_id', DB::raw('MAX(todo_date) as last_todo_date'))
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->groupBy('contact_id');

        $query = Contact::leftJoinSub($latestTodo, 'lt', 'contacts.id', '=', 'lt.contact_id')
            ->select([
                'contacts.id', 'contacts.name', 'contacts.user_id', 'contacts.status_id',
                'lt.last_todo_date',
                DB::raw('DATEDIFF(CURDATE(), lt.last_todo_date) as days_inactive'),
            ])
            ->with(['user:id,name', 'status:id,name'])
            ->when($uid,                            fn ($q) => $q->where('contacts.user_id', $uid))
            ->when($request->filled('status_id'),   fn ($q) => $q->where('contacts.status_id', $request->status_id))
            ->when($request->filled('industry_id'), fn ($q) => $q->where('contacts.industry_id', $request->industry_id))
            ->when($search, fn ($q) => $q->where('contacts.name', 'like', "%{$search}%"));

        match ($health) {
            'active'  => $query->whereNotNull('lt.last_todo_date')
                               ->whereRaw('DATEDIFF(CURDATE(), lt.last_todo_date) < 30'),
            'at_risk' => $query->whereNotNull('lt.last_todo_date')
                               ->whereRaw('DATEDIFF(CURDATE(), lt.last_todo_date) BETWEEN 30 AND 60'),
            'dormant' => $query->where(fn ($q) =>
                             $q->whereNull('lt.last_todo_date')
                               ->orWhereRaw('DATEDIFF(CURDATE(), lt.last_todo_date) > 60')),
            default   => null,
        };

        match ($sortBy) {
            'days_inactive'  => $query
                ->orderByRaw('lt.last_todo_date IS NULL ' . ($sortDir === 'desc' ? 'DESC' : 'ASC'))
                ->orderByRaw('lt.last_todo_date '        . ($sortDir === 'desc' ? 'ASC'  : 'DESC')),
            'last_todo_date' => $query
                ->orderByRaw('lt.last_todo_date IS NULL DESC')
                ->orderBy('lt.last_todo_date', $sortDir),
            default          => $query->orderBy('contacts.name', $sortDir),
        };

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($c) {
            $days = $c->last_todo_date ? (int) $c->days_inactive : null;
            return [
                'id'             => $c->id,
                'name'           => $c->name,
                'user_name'      => $c->user?->name  ?? '-',
                'status_name'    => $c->status?->name ?? '-',
                'last_todo_date' => $c->last_todo_date,
                'days_inactive'  => $days,
                'health'         => $this->healthBadge($days),
            ];
        });

        // Summary counts via a single SQL aggregation (efficient, no PHP-side collection)
        $summaryLatest = ToDo::select('contact_id', DB::raw('MAX(todo_date) as last_todo_date'))
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->groupBy('contact_id');

        $summary = DB::table('contacts as c')
            ->leftJoinSub($summaryLatest, 'lt', 'c.id', '=', 'lt.contact_id')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN lt.last_todo_date IS NOT NULL AND DATEDIFF(CURDATE(), lt.last_todo_date) < 30              THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN lt.last_todo_date IS NOT NULL AND DATEDIFF(CURDATE(), lt.last_todo_date) BETWEEN 30 AND 60 THEN 1 ELSE 0 END) as at_risk,
                SUM(CASE WHEN lt.last_todo_date IS NULL     OR  DATEDIFF(CURDATE(), lt.last_todo_date) > 60              THEN 1 ELSE 0 END) as dormant,
                SUM(CASE WHEN lt.last_todo_date IS NULL THEN 1 ELSE 0 END) as no_activity
            ")
            ->first();

        return response()->json([
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
            ],
            'summary' => [
                'total'       => (int) ($summary->total       ?? 0),
                'active'      => (int) ($summary->active      ?? 0),
                'at_risk'     => (int) ($summary->at_risk     ?? 0),
                'dormant'     => (int) ($summary->dormant     ?? 0),
                'no_activity' => (int) ($summary->no_activity ?? 0),
            ],
        ]);
    }

    private function healthBadge(?int $days): string
    {
        return match (true) {
            is_null($days) => 'no_activity',
            $days < 30     => 'active',
            $days <= 60    => 'at_risk',
            default        => 'dormant',
        };
    }
}
