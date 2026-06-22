<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\KpiTarget;
use App\Models\ToDo;
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
            return $request->filled('user_id') ? (int) $request->input('user_id') : null;
        }
        return $u->id;
    }

    // COALESCE(last_contacted_at, updated_at) — handles contacts that predate the
    // last_contacted_at column, falling back to updated_at for those rows.
    private function lastTouchedExpr(string $alias = 'c'): string
    {
        return "COALESCE({$alias}.last_contacted_at, {$alias}.updated_at)";
    }

    // ── 1. Summary KPI cards ──────────────────────────────────────────────────

    public function summary(Request $request)
    {
        $uid     = $this->resolveUserId($request);
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super-admin']);
        $touched = $this->lastTouchedExpr();

        // Non-raw contacts with no genuine interaction in 60+ days
        $neglected = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereNull('c.deleted_at')
            ->whereRaw("DATEDIFF(NOW(), {$touched}) >= 60")
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->count();

        // Open deal pipeline weighted by probability
        $pipelineValue = Deal::where('status', 'open')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->selectRaw('SUM(value * COALESCE(probability, 50) / 100) as weighted')
            ->value('weighted');

        // Agents carrying more than 1.5× the average contact load (admin-only, all-agents view)
        $overloadedAgents = null;
        if ($isAdmin && ! $uid) {
            $counts           = Contact::selectRaw('user_id, COUNT(*) as cnt')->groupBy('user_id')->pluck('cnt');
            $avg              = $counts->count() > 0 ? $counts->avg() : 0;
            $overloadedAgents = $counts->filter(fn ($c) => $c > $avg * 1.5)->count();
        }

        // Non-raw contacts with no genuine interaction in 30+ days
        $unworkedOpps = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereNull('c.deleted_at')
            ->whereRaw("DATEDIFF(NOW(), {$touched}) >= 30")
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->count();

        return response()->json([
            'neglected'         => $neglected,
            'pipeline_value'    => $pipelineValue !== null ? round((float) $pipelineValue, 2) : null,
            'overloaded_agents' => $overloadedAgents,
            'unworked_opps'     => $unworkedOpps,
        ]);
    }

    // ── 2. Pipeline by close month ────────────────────────────────────────────
    //    Groups open deals by expected close month. This is a weighted bucket
    //    summary — not a statistical forecast.

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
            if (! isset($byMonth[$key])) {
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

    // ── 3. Neglected contacts ─────────────────────────────────────────────────
    //    Non-raw contacts with no completed todo or follow-up in threshold days.
    //    Uses last_contacted_at (falls back to updated_at for legacy rows).

    public function atRisk(Request $request)
    {
        $uid       = $this->resolveUserId($request);
        $threshold = max(1, (int) $request->input('threshold', 60));
        $touched   = $this->lastTouchedExpr();

        $contacts = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->join('users as u', 'c.user_id', '=', 'u.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereNull('c.deleted_at')
            ->whereRaw("DATEDIFF(NOW(), {$touched}) >= ?", [$threshold])
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->select([
                'c.id',
                'c.name',
                's.name as status_name',
                'u.name as owner_name',
                DB::raw("DATEDIFF(NOW(), {$touched}) as days_since_update"),
            ])
            ->orderByDesc('days_since_update')
            ->limit(30)
            ->get();

        return response()->json($contacts);
    }

    // ── 4. Agent coverage load ────────────────────────────────────────────────

    public function pace(Request $request)
    {
        $uid     = $this->resolveUserId($request);
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        $rows = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->join('users as u', 'c.user_id', '=', 'u.id')
            ->whereNull('c.deleted_at')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->when(! $isAdmin && ! $uid, fn ($q) => $q->where('c.user_id', $user->id))
            ->groupBy('c.user_id', 'u.name')
            ->selectRaw("
                c.user_id,
                u.name,
                COUNT(*) as total,
                SUM(CASE WHEN s.name LIKE '%Raw%' THEN 1 ELSE 0 END) as raw_count,
                SUM(CASE WHEN s.name NOT LIKE '%Raw%' THEN 1 ELSE 0 END) as engaged_count
            ")
            ->orderByDesc('total')
            ->get();

        $maxTotal = $rows->max('total') ?: 1;

        $result = $rows->map(fn ($row) => [
            'user_id'       => $row->user_id,
            'name'          => $row->name,
            'total'         => (int) $row->total,
            'raw_count'     => (int) $row->raw_count,
            'engaged_count' => (int) $row->engaged_count,
            'load_pct'      => round($row->total / $maxTotal * 100, 1),
            'engaged_pct'   => $row->total > 0 ? round($row->engaged_count / $row->total * 100, 1) : 0,
        ]);

        return response()->json($result);
    }

    // ── 5. Unworked segment opportunities ────────────────────────────────────
    //    By industry: engaged contacts with no interaction in 30+ days.
    //    Uses last_contacted_at (falls back to updated_at for legacy rows).

    public function overdueRisk(Request $request)
    {
        $uid     = $this->resolveUserId($request);
        $touched = $this->lastTouchedExpr();

        $rows = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->leftJoin('contact_industries as i', 'c.industry_id', '=', 'i.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereNull('c.deleted_at')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->groupBy('c.industry_id', 'i.name')
            ->selectRaw("
                c.industry_id,
                COALESCE(i.name, 'Unassigned') as industry_name,
                COUNT(*) as total,
                SUM(CASE WHEN DATEDIFF(NOW(), {$touched}) >= 30 THEN 1 ELSE 0 END) as unworked
            ")
            ->having('total', '>=', 1)
            ->orderByDesc('unworked')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'industry_id'   => $r->industry_id,
                'industry_name' => $r->industry_name,
                'total'         => (int) $r->total,
                'unworked'      => (int) $r->unworked,
                'unworked_pct'  => $r->total > 0 ? round($r->unworked / $r->total * 100, 1) : 0,
            ]);

        return response()->json($rows);
    }

    // ── 6. Open deals with honest signals ────────────────────────────────────
    //    Returns the agent-set probability unchanged plus observable signals
    //    (days to close, completed activity) so the UI can present facts rather
    //    than a fake adjusted score.

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
            $daysToClose = null;
            if ($d->expected_close_date) {
                $daysToClose = (int) $today->diffInDays($d->expected_close_date, false);
            }

            // Only count completed todos — created-but-not-done todos are noise
            $recentActivity = $d->contact_id
                ? ToDo::where('contact_id', $d->contact_id)
                    ->where('completion_status', 'completed')
                    ->where('completed_at', '>=', $today->copy()->subDays(30))
                    ->count()
                : 0;

            return [
                'id'                  => $d->id,
                'title'               => $d->title,
                'contact_name'        => $d->contact?->name ?? '—',
                'contact_id'          => $d->contact_id,
                'value'               => (float) $d->value,
                'expected_close_date' => $d->expected_close_date?->format('Y-m-d'),
                'probability'         => (int) ($d->probability ?? 50),
                'recent_activity'     => $recentActivity,
                'days_to_close'       => $daysToClose,
            ];
        });

        return response()->json($result);
    }

    // ── 7. Historical win rates ───────────────────────────────────────────────
    //    Actual closed-deal win rates from the selected period, by agent and
    //    by industry. Requires won/lost deals — empty if none exist yet.

    public function winRates(Request $request)
    {
        $uid  = $this->resolveUserId($request);
        $from = $request->input('from', Carbon::now()->subYear()->toDateString());
        $to   = $request->input('to',   Carbon::now()->toDateString());

        $base = DB::table('deals as d')
            ->whereIn('d.status', ['won', 'lost'])
            ->whereBetween('d.updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->when($uid, fn ($q) => $q->where('d.user_id', $uid));

        $byAgent = (clone $base)
            ->join('users as u', 'd.user_id', '=', 'u.id')
            ->groupBy('d.user_id', 'u.name')
            ->selectRaw("
                d.user_id,
                u.name,
                COUNT(*) as total,
                SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) as won,
                ROUND(SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as win_rate
            ")
            ->orderByDesc('total')
            ->get();

        $byIndustry = (clone $base)
            ->join('contacts as c', 'd.contact_id', '=', 'c.id')
            ->leftJoin('contact_industries as i', 'c.industry_id', '=', 'i.id')
            ->groupBy('c.industry_id', 'i.name')
            ->selectRaw("
                c.industry_id,
                COALESCE(i.name, 'Unassigned') as industry_name,
                COUNT(*) as total,
                SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) as won,
                ROUND(SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as win_rate
            ")
            ->having('total', '>=', 2)
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'by_agent'    => $byAgent,
            'by_industry' => $byIndustry,
        ]);
    }

    // ── 8. Deal velocity ──────────────────────────────────────────────────────
    //    Average days-to-close from historical won deals, then flags open deals
    //    that are aging beyond that benchmark.

    public function dealVelocity(Request $request)
    {
        $uid  = $this->resolveUserId($request);
        $from = $request->input('from', Carbon::now()->subYear()->toDateString());
        $to   = $request->input('to',   Carbon::now()->toDateString());

        $avgDays = DB::table('deals')
            ->where('status', 'won')
            ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days, COUNT(*) as sample_size')
            ->first();

        $benchmark  = $avgDays?->avg_days ? (int) round($avgDays->avg_days) : null;
        $sampleSize = (int) ($avgDays?->sample_size ?? 0);

        $today     = Carbon::today();
        $openDeals = Deal::with(['contact:id,name'])
            ->where('status', 'open')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(function ($d) use ($today, $benchmark) {
                $daysOpen     = (int) Carbon::parse($d->created_at)->diffInDays($today);
                $vsBenchmark  = $benchmark !== null ? $daysOpen - $benchmark : null;
                $isStalling   = $benchmark !== null && $daysOpen > $benchmark * 1.5;

                return [
                    'id'           => $d->id,
                    'title'        => $d->title,
                    'contact_name' => $d->contact?->name ?? '—',
                    'value'        => (float) $d->value,
                    'days_open'    => $daysOpen,
                    'vs_benchmark' => $vsBenchmark,
                    'is_stalling'  => $isStalling,
                ];
            });

        return response()->json([
            'benchmark_days' => $benchmark,
            'sample_size'    => $sampleSize,
            'stalling_count' => $openDeals->where('is_stalling', true)->count(),
            'deals'          => $openDeals->values(),
        ]);
    }

    // ── 9. Pipeline coverage vs KPI targets ──────────────────────────────────
    //    Compares each agent's weighted open pipeline to their won_deal_value
    //    KPI target. Agents without a target show pipeline only.

    public function pipelineCoverage(Request $request)
    {
        $uid  = $this->resolveUserId($request);
        $user = $request->user();

        // Weighted pipeline per agent
        $pipeline = Deal::where('status', 'open')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->when(! $user->hasAnyRole(['admin', 'super-admin']) && ! $uid, fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('user_id, SUM(value * COALESCE(probability, 50) / 100) as weighted_value')
            ->groupBy('user_id')
            ->pluck('weighted_value', 'user_id');

        // KPI targets for won_deal_value
        $targets = KpiTarget::where('metric', 'won_deal_value')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->when(! $user->hasAnyRole(['admin', 'super-admin']) && ! $uid, fn ($q) => $q->where('user_id', $user->id))
            ->pluck('target_value', 'user_id');

        $userIds = $pipeline->keys()->merge($targets->keys())->unique();
        $names   = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

        $result = $userIds->map(function ($userId) use ($pipeline, $targets, $names) {
            $weighted = (float) ($pipeline[$userId] ?? 0);
            $target   = (float) ($targets[$userId] ?? 0);

            return [
                'user_id'           => $userId,
                'name'              => $names[$userId] ?? 'Unknown',
                'weighted_pipeline' => round($weighted, 2),
                'target'            => round($target, 2),
                'coverage_pct'      => $target > 0 ? round($weighted / $target * 100, 1) : null,
                'gap'               => $target > 0 ? round(max(0, $target - $weighted), 2) : null,
            ];
        })->sortByDesc('weighted_pipeline')->values();

        return response()->json($result);
    }
}
