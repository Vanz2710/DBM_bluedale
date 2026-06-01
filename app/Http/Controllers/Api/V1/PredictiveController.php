<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUp;
use App\Models\KpiTarget;
use App\Models\Project;
use App\Models\ToDo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PredictiveController extends Controller
{
    // ── Shared helpers ────────────────────────────────────────────────────────

    private function resolveUserId(Request $request): ?int
    {
        $u = $request->user();
        if ($u->hasAnyRole(['admin', 'super-admin'])) {
            return $request->filled('user_id') ? (int) $request->user_id : null;
        }
        return $u->id;
    }

    private function metricActual(int $userId, string $metric, string $from, string $to): float
    {
        $fromDt = "{$from} 00:00:00";
        $toDt   = "{$to} 23:59:59";

        return match ($metric) {
            'new_contacts'        => Contact::where('user_id', $userId)->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'todos_completed'     => ToDo::where('user_id', $userId)->where('completion_status', 'completed')->whereBetween('completed_at', [$fromDt, $toDt])->count(),
            'followups_completed' => FollowUp::whereHas('todo', fn ($q) => $q->where('user_id', $userId))->where('completion_status', 'completed')->whereBetween('completed_at', [$fromDt, $toDt])->count(),
            'projects_created'    => Project::where('user_id', $userId)->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'deals_created'       => Deal::where('user_id', $userId)->whereBetween('created_at', [$fromDt, $toDt])->count(),
            'deals_won'           => Deal::where('user_id', $userId)->where('status', 'won')->whereBetween('updated_at', [$fromDt, $toDt])->count(),
            'won_deal_value'      => (float) Deal::where('user_id', $userId)->where('status', 'won')->whereBetween('updated_at', [$fromDt, $toDt])->sum('value'),
            default               => 0,
        };
    }

    // ── 1. Summary KPI cards ──────────────────────────────────────────────────

    public function summary(Request $request)
    {
        $uid     = $this->resolveUserId($request);
        $today   = Carbon::today()->toDateString();
        $in7     = Carbon::today()->addDays(7)->toDateString();
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super-admin']);

        // Contacts with no todo activity in 30+ days
        $latestTodo = ToDo::select('contact_id', DB::raw('MAX(todo_date) as last_todo'))
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->groupBy('contact_id');

        $atRisk = DB::table('contacts as c')
            ->leftJoinSub($latestTodo, 'lt', 'c.id', '=', 'lt.contact_id')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->where(fn ($q) =>
                $q->whereNull('lt.last_todo')
                  ->orWhereRaw('DATEDIFF(CURDATE(), lt.last_todo) >= 30')
            )
            ->count();

        // Open deal pipeline weighted by probability
        $pipelineValue = Deal::where('status', 'open')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->selectRaw('SUM(value * COALESCE(probability, 50) / 100) as weighted')
            ->value('weighted');

        // Pending todos due in the next 7 days
        $overdueRisk = ToDo::where('completion_status', 'pending')
            ->whereBetween('todo_date', [$today, $in7])
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->count();

        // Agents below 80% pace on any KPI target (admin, no user filter)
        $agentsOffPace = null;
        if ($isAdmin && !$uid) {
            $monthStart  = Carbon::now()->startOfMonth()->toDateString();
            $monthEnd    = Carbon::now()->endOfMonth()->toDateString();
            $daysInMonth = Carbon::now()->daysInMonth;
            $daysPassed  = max(1, Carbon::now()->day);
            $offPaceCount = 0;

            foreach (User::all() as $u) {
                $targets = KpiTarget::where('user_id', $u->id)->get();
                if ($targets->isEmpty()) continue;
                foreach ($targets as $t) {
                    $actual    = $this->metricActual($u->id, $t->metric, $monthStart, $monthEnd);
                    $projected = ($actual / $daysPassed) * $daysInMonth;
                    $pacePct   = $t->target_value > 0 ? ($projected / $t->target_value) * 100 : 100;
                    if ($pacePct < 80) {
                        $offPaceCount++;
                        break;
                    }
                }
            }
            $agentsOffPace = $offPaceCount;
        }

        return response()->json([
            'at_risk'         => $atRisk,
            'pipeline_value'  => $pipelineValue ? round((float) $pipelineValue, 2) : 0,
            'agents_off_pace' => $agentsOffPace,
            'overdue_risk'    => $overdueRisk,
        ]);
    }

    // ── 2. Revenue pipeline forecast ──────────────────────────────────────────

    public function forecast(Request $request)
    {
        $uid = $this->resolveUserId($request);

        $deals = Deal::where('status', 'open')
            ->whereNotNull('expected_close_date')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->get();

        $byMonth = [];
        foreach ($deals as $d) {
            $key = Carbon::parse($d->expected_close_date)->format('Y-m');
            if (!isset($byMonth[$key])) {
                $byMonth[$key] = [
                    'month'          => $key,
                    'label'          => Carbon::parse($d->expected_close_date)->format('M Y'),
                    'expected_value' => 0,
                    'total_value'    => 0,
                    'deal_count'     => 0,
                ];
            }
            $byMonth[$key]['total_value']    += (float) $d->value;
            $byMonth[$key]['expected_value'] += (float) $d->value * ((float) ($d->probability ?? 50) / 100);
            $byMonth[$key]['deal_count']++;
        }

        ksort($byMonth);
        $result = array_values($byMonth);
        foreach ($result as &$r) {
            $r['total_value']    = round($r['total_value'], 2);
            $r['expected_value'] = round($r['expected_value'], 2);
        }

        return response()->json($result);
    }

    // ── 3. At-risk contacts ───────────────────────────────────────────────────

    public function atRisk(Request $request)
    {
        $uid       = $this->resolveUserId($request);
        $threshold = max(1, (int) $request->get('threshold', 30));

        $latestTodo = ToDo::select('contact_id', DB::raw('MAX(todo_date) as last_todo'))
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->groupBy('contact_id');

        $contacts = DB::table('contacts as c')
            ->leftJoinSub($latestTodo, 'lt', 'c.id', '=', 'lt.contact_id')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->where(fn ($q) =>
                $q->whereNull('lt.last_todo')
                  ->orWhereRaw('DATEDIFF(CURDATE(), lt.last_todo) >= ?', [$threshold])
            )
            ->select([
                'c.id',
                'c.name',
                DB::raw('COALESCE(DATEDIFF(CURDATE(), lt.last_todo), DATEDIFF(CURDATE(), DATE(c.created_at))) as days_since_activity'),
                'lt.last_todo as last_activity_date',
            ])
            ->orderByDesc('days_since_activity')
            ->limit(30)
            ->get();

        return response()->json($contacts);
    }

    // ── 4. Agent pace-to-target ───────────────────────────────────────────────

    public function pace(Request $request)
    {
        $uid         = $this->resolveUserId($request);
        $user        = $request->user();
        $isAdmin     = $user->hasAnyRole(['admin', 'super-admin']);
        $monthStart  = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd    = Carbon::now()->endOfMonth()->toDateString();
        $daysInMonth = Carbon::now()->daysInMonth;
        $daysPassed  = max(1, Carbon::now()->day);

        $users = ($isAdmin && !$uid)
            ? User::orderBy('name')->get()
            : User::where('id', $uid ?? $user->id)->get();

        $result = [];
        foreach ($users as $u) {
            $targets = KpiTarget::where('user_id', $u->id)->get();
            if ($targets->isEmpty()) continue;

            $metrics   = [];
            $pacePcts  = [];

            foreach ($targets as $t) {
                $actual    = $this->metricActual($u->id, $t->metric, $monthStart, $monthEnd);
                $projected = round(($actual / $daysPassed) * $daysInMonth, 1);
                $pacePct   = $t->target_value > 0
                    ? min(150, round(($projected / $t->target_value) * 100, 1))
                    : 100;

                $pacePcts[] = $pacePct;
                $metrics[]  = [
                    'metric'    => $t->metric,
                    'target'    => (float) $t->target_value,
                    'actual'    => $actual,
                    'projected' => $projected,
                    'pace_pct'  => $pacePct,
                ];
            }

            $result[] = [
                'user_id'  => $u->id,
                'name'     => $u->name,
                'pace_pct' => count($pacePcts) ? round(array_sum($pacePcts) / count($pacePcts), 1) : null,
                'metrics'  => $metrics,
            ];
        }

        return response()->json($result);
    }

    // ── 5. Overdue risk (next 7 days) ─────────────────────────────────────────

    public function overdueRisk(Request $request)
    {
        $uid   = $this->resolveUserId($request);
        $today = Carbon::today()->toDateString();
        $in7   = Carbon::today()->addDays(7)->toDateString();

        $todos = ToDo::with(['contact:id,name'])
            ->where('completion_status', 'pending')
            ->whereBetween('todo_date', [$today, $in7])
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->orderBy('todo_date')
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'title'        => $t->todo_remark ?? 'Untitled task',
                'todo_date'    => $t->todo_date?->format('Y-m-d'),
                'contact_name' => $t->contact?->name ?? '—',
                'contact_id'   => $t->contact_id,
            ]);

        return response()->json($todos);
    }

    // ── 6. Lead conversion by segment ────────────────────────────────────────

    public function segments(Request $request)
    {
        $uid = $this->resolveUserId($request);

        $dimensions = [
            ['label' => 'Industry', 'col' => 'industry_id', 'table' => 'contact_industries'],
            ['label' => 'Type',     'col' => 'type_id',     'table' => 'contact_types'],
        ];

        $result = [];
        foreach ($dimensions as $dim) {
            $rows = DB::table('contacts as c')
                ->join("{$dim['table']} as d", "c.{$dim['col']}", '=', 'd.id')
                ->leftJoin('deals as dl', fn ($j) =>
                    $j->on('dl.contact_id', '=', 'c.id')->where('dl.status', 'won')
                )
                ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
                ->whereNotNull("c.{$dim['col']}")
                ->groupBy("c.{$dim['col']}", 'd.name')
                ->selectRaw("
                    c.{$dim['col']} as segment_id,
                    d.name          as name,
                    COUNT(DISTINCT c.id)  as total,
                    COUNT(DISTINCT dl.id) as won
                ")
                ->having('total', '>=', 3)
                ->orderByDesc('won')
                ->limit(5)
                ->get();

            foreach ($rows as $row) {
                $rate = $row->total > 0 ? round($row->won / $row->total * 100, 1) : 0;
                $result[] = [
                    'key'       => $dim['label'] . '_' . $row->segment_id,
                    'name'      => $row->name,
                    'dimension' => $dim['label'],
                    'total'     => (int) $row->total,
                    'won'       => (int) $row->won,
                    'rate'      => $rate,
                ];
            }
        }

        usort($result, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return response()->json($result);
    }

    // ── 7. Deal win probability (auto-scored) ─────────────────────────────────

    public function deals(Request $request)
    {
        $uid   = $this->resolveUserId($request);
        $today = Carbon::today();

        $deals = Deal::with(['contact:id,name'])
            ->where('status', 'open')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->orderBy('expected_close_date')
            ->limit(50)
            ->get();

        $result = $deals->map(function ($d) use ($today) {
            $base = (float) ($d->probability ?? 50);

            // Urgency: penalise overdue close dates, reward upcoming ones
            $urgencyBonus = 0;
            $daysToClose  = null;
            if ($d->expected_close_date) {
                $daysToClose  = $today->diffInDays($d->expected_close_date, false);
                $urgencyBonus = $daysToClose < 0 ? -20 : ($daysToClose <= 14 ? 5 : 0);
            }

            // Activity: recent todos on the contact in the last 30 days
            $recentActivity = $d->contact_id
                ? ToDo::where('contact_id', $d->contact_id)
                    ->where('todo_date', '>=', $today->copy()->subDays(30)->toDateString())
                    ->count()
                : 0;
            $activityBonus = $recentActivity >= 3 ? 10 : ($recentActivity >= 1 ? 5 : -10);

            $adjusted = min(100, max(0, $base + $urgencyBonus + $activityBonus));

            return [
                'id'                  => $d->id,
                'title'               => $d->title,
                'contact_name'        => $d->contact?->name ?? '—',
                'contact_id'          => $d->contact_id,
                'value'               => (float) $d->value,
                'expected_close_date' => $d->expected_close_date?->format('Y-m-d'),
                'probability'         => (int) round($adjusted),
                'base_probability'    => (int) $base,
                'recent_activity'     => $recentActivity,
                'days_to_close'       => $daysToClose,
            ];
        });

        return response()->json($result);
    }

    // ── 8. Activity trend + 2-week projection ────────────────────────────────

    public function trend(Request $request)
    {
        $uid = $this->resolveUserId($request);

        // 6 actual weeks (oldest first)
        $actual = [];
        for ($i = 5; $i >= 0; $i--) {
            $start  = Carbon::now()->startOfWeek()->subWeeks($i)->toDateString();
            $end    = Carbon::now()->startOfWeek()->subWeeks($i)->endOfWeek()->toDateString();
            $fromDt = "{$start} 00:00:00";
            $toDt   = "{$end} 23:59:59";

            $actual[] = [
                'week'                => Carbon::parse($start)->format('d M'),
                'week_start'          => $start,
                'todos_completed'     => ToDo::where('completion_status', 'completed')
                    ->whereBetween('completed_at', [$fromDt, $toDt])
                    ->when($uid, fn ($q) => $q->where('user_id', $uid))
                    ->count(),
                'followups_completed' => FollowUp::where('completion_status', 'completed')
                    ->whereBetween('completed_at', [$fromDt, $toDt])
                    ->when($uid, fn ($q) =>
                        $q->whereHas('todo', fn ($q2) => $q2->where('user_id', $uid))
                    )
                    ->count(),
                'contacts_added'      => Contact::whereBetween('created_at', [$fromDt, $toDt])
                    ->when($uid, fn ($q) => $q->where('user_id', $uid))
                    ->count(),
                'projected'           => false,
            ];
        }

        // Weighted projection from the last 3 actual weeks (weights: 1, 2, 3)
        $last3      = array_slice($actual, -3);
        $weights    = [1, 2, 3];
        $weightSum  = 6;
        $projection = [];
        foreach (['todos_completed', 'followups_completed', 'contacts_added'] as $metric) {
            $weighted = 0;
            foreach ($last3 as $i => $w) {
                $weighted += $w[$metric] * $weights[$i];
            }
            $projection[$metric] = (int) round($weighted / $weightSum);
        }

        $result = $actual;
        for ($i = 1; $i <= 2; $i++) {
            $weekStart = Carbon::now()->startOfWeek()->addWeeks($i);
            $result[]  = [
                'week'                => $weekStart->format('d M'),
                'week_start'          => $weekStart->toDateString(),
                'todos_completed'     => $projection['todos_completed'],
                'followups_completed' => $projection['followups_completed'],
                'contacts_added'      => $projection['contacts_added'],
                'projected'           => true,
            ];
        }

        return response()->json($result);
    }
}
