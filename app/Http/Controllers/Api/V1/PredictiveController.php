<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUp;
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
            return $request->filled('user_id') ? (int) $request->user_id : null;
        }
        return $u->id;
    }

    // ── 1. Summary KPI cards ──────────────────────────────────────────────────

    public function summary(Request $request)
    {
        $uid     = $this->resolveUserId($request);
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super-admin']);

        // Non-raw contacts not updated in 60+ days
        $neglected = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereRaw('DATEDIFF(NOW(), c.updated_at) >= 60')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->count();

        // Open deal pipeline weighted by probability
        $pipelineValue = Deal::where('status', 'open')
            ->when($uid, fn ($q) => $q->where('user_id', $uid))
            ->selectRaw('SUM(value * COALESCE(probability, 50) / 100) as weighted')
            ->value('weighted');

        // Agents carrying more than 1.5× the average contact load (admin-only)
        $overloadedAgents = null;
        if ($isAdmin && ! $uid) {
            $counts           = Contact::selectRaw('user_id, COUNT(*) as cnt')->groupBy('user_id')->pluck('cnt');
            $avg              = $counts->count() > 0 ? $counts->avg() : 0;
            $overloadedAgents = $counts->filter(fn ($c) => $c > $avg * 1.5)->count();
        }

        // Non-raw contacts not updated in 30+ days
        $unworkedOpps = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereRaw('DATEDIFF(NOW(), c.updated_at) >= 30')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->count();

        return response()->json([
            'neglected'         => $neglected,
            'pipeline_value'    => $pipelineValue ? round((float) $pipelineValue, 2) : 0,
            'overloaded_agents' => $overloadedAgents,
            'unworked_opps'     => $unworkedOpps,
        ]);
    }

    // ── 2. Revenue pipeline forecast (unchanged) ──────────────────────────────

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
    //    Non-raw contacts (Potential, Existing Client, etc.) that have not been
    //    updated in 60+ days — contacts that were qualified but then forgotten.

    public function atRisk(Request $request)
    {
        $uid       = $this->resolveUserId($request);
        $threshold = max(1, (int) $request->get('threshold', 60));

        $contacts = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->join('users as u', 'c.user_id', '=', 'u.id')
            ->where('s.name', 'not like', '%Raw%')
            ->whereRaw('DATEDIFF(NOW(), c.updated_at) >= ?', [$threshold])
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->select([
                'c.id',
                'c.name',
                's.name as status_name',
                'u.name as owner_name',
                DB::raw('DATEDIFF(NOW(), c.updated_at) as days_since_update'),
            ])
            ->orderByDesc('days_since_update')
            ->limit(30)
            ->get();

        return response()->json($contacts);
    }

    // ── 4. Agent coverage load ────────────────────────────────────────────────
    //    Total contacts per agent, split into raw vs actionable (engaged).

    public function pace(Request $request)
    {
        $uid     = $this->resolveUserId($request);
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        $rows = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->join('users as u', 'c.user_id', '=', 'u.id')
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
    //    By industry: how many actionable contacts haven't been touched in 30+ days.

    public function overdueRisk(Request $request)
    {
        $uid       = $this->resolveUserId($request);
        $threshold = 30;

        $rows = DB::table('contacts as c')
            ->join('contact_statuses as s', 'c.status_id', '=', 's.id')
            ->join('contact_industries as i', 'c.industry_id', '=', 'i.id')
            ->where('s.name', 'not like', '%Raw%')
            ->when($uid, fn ($q) => $q->where('c.user_id', $uid))
            ->groupBy('c.industry_id', 'i.name')
            ->selectRaw("
                c.industry_id,
                i.name as industry_name,
                COUNT(*) as total,
                SUM(CASE WHEN DATEDIFF(NOW(), c.updated_at) >= {$threshold} THEN 1 ELSE 0 END) as unworked
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

    // ── 6. Lead conversion by segment (unchanged) ────────────────────────────

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
                $rate     = $row->total > 0 ? round($row->won / $row->total * 100, 1) : 0;
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

    // ── 7. Deal win probability (unchanged) ──────────────────────────────────

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

            $urgencyBonus = 0;
            $daysToClose  = null;
            if ($d->expected_close_date) {
                $daysToClose  = $today->diffInDays($d->expected_close_date, false);
                $urgencyBonus = $daysToClose < 0 ? -20 : ($daysToClose <= 14 ? 5 : 0);
            }

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

    // ── 8. Portfolio growth trend ─────────────────────────────────────────────
    //    Weekly new contacts added over 8 weeks + 2-week weighted projection.

    public function trend(Request $request)
    {
        $uid = $this->resolveUserId($request);

        $actual = [];
        for ($i = 7; $i >= 0; $i--) {
            $start  = Carbon::now()->startOfWeek()->subWeeks($i)->toDateString();
            $end    = Carbon::now()->startOfWeek()->subWeeks($i)->endOfWeek()->toDateString();

            $actual[] = [
                'week'           => Carbon::parse($start)->format('d M'),
                'week_start'     => $start,
                'contacts_added' => Contact::whereBetween('created_at', ["{$start} 00:00:00", "{$end} 23:59:59"])
                    ->when($uid, fn ($q) => $q->where('user_id', $uid))
                    ->count(),
                'projected'      => false,
            ];
        }

        // Weighted average of last 3 weeks (weights: 1, 2, 3)
        $last3     = array_slice($actual, -3);
        $weights   = [1, 2, 3];
        $weighted  = 0;
        foreach ($last3 as $i => $w) {
            $weighted += $w['contacts_added'] * $weights[$i];
        }
        $proj = (int) round($weighted / 6);

        for ($i = 1; $i <= 2; $i++) {
            $weekStart = Carbon::now()->startOfWeek()->addWeeks($i);
            $actual[]  = [
                'week'           => $weekStart->format('d M'),
                'week_start'     => $weekStart->toDateString(),
                'contacts_added' => $proj,
                'projected'      => true,
            ];
        }

        return response()->json($actual);
    }
}
