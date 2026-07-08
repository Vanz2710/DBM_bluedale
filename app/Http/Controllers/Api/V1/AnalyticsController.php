<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ToDo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private function userId(Request $request): ?int
    {
        $u = $request->user();
        if ($u->hasAnyRole(['admin', 'super-admin'])) {
            return $request->filled('user_id') ? (int) $request->user_id : null;
        }
        return $u->id;
    }

    public function summary(Request $request)
    {
        $uid     = $this->userId($request);
        $isAdmin = $request->user()->hasAnyRole(['admin', 'super-admin']);

        // Scoped base builders — use `contacts.user_id` to stay safe across joins
        $cBase = fn () => Contact::when($uid, fn ($q) => $q->where('contacts.user_id', $uid));
        $tBase = fn () => ToDo::when($uid,    fn ($q) => $q->where('user_id', $uid));

        $totalContacts = $cBase()->count();

        // This month vs last month
        $thisMonth = $cBase()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lastMonth = $cBase()->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();

        // Status distribution
        $byStatus = $cBase()
            ->join('contact_statuses', 'contacts.status_id', '=', 'contact_statuses.id')
            ->select('contact_statuses.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_statuses.name')->orderByDesc('count')->get();

        // Industry distribution
        $byIndustry = $cBase()
            ->join('contact_industries', 'contacts.industry_id', '=', 'contact_industries.id')
            ->select('contact_industries.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_industries.name')->orderByDesc('count')->get();

        // Category/product distribution
        $byCategory = $cBase()
            ->join('contact_categories', 'contacts.category_id', '=', 'contact_categories.id')
            ->select('contact_categories.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_categories.name')->orderByDesc('count')->get();

        // Type distribution
        $byType = $cBase()
            ->join('contact_types', 'contacts.type_id', '=', 'contact_types.id')
            ->select('contact_types.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_types.name')->orderByDesc('count')->get();

        // User distribution — only meaningful for admins
        $byUser = $isAdmin
            ? Contact::join('users', 'contacts.user_id', '=', 'users.id')
                ->select('users.name as label', DB::raw('count(*) as count'))
                ->groupBy('users.name')->orderByDesc('count')->get()
            : collect();

        // Monthly contacts — last 12 months, fill zeros for empty months
        $contactRaw = $cBase()->select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as sort_key"),
            DB::raw('count(*) as count')
        )->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
         ->groupBy('sort_key')
         ->pluck('count', 'sort_key');

        $byMonth = collect();
        for ($i = 11; $i >= 0; $i--) {
            $m   = now()->subMonths($i);
            $key = $m->format('Y-m');
            $byMonth->push(['label' => $m->format('M Y'), 'count' => (int) ($contactRaw[$key] ?? 0)]);
        }

        // Monthly tasks — last 12 months, fill zeros for empty months
        $todoRaw = $tBase()->select(
            DB::raw("DATE_FORMAT(todo_date, '%Y-%m') as sort_key"),
            DB::raw('count(*) as count')
        )->where('todo_date', '>=', now()->subMonths(11)->startOfMonth())
         ->groupBy('sort_key')
         ->pluck('count', 'sort_key');

        $byTasks = collect();
        for ($i = 11; $i >= 0; $i--) {
            $m   = now()->subMonths($i);
            $key = $m->format('Y-m');
            $byTasks->push(['label' => $m->format('M Y'), 'count' => (int) ($todoRaw[$key] ?? 0)]);
        }

        // Unassigned — only meaningful for admins
        $unassigned = $isAdmin ? Contact::whereNull('user_id')->count() : 0;

        // At-a-glance derived from already-fetched $byStatus
        $activeCount   = 0;
        $existingCount = 0;
        $rawCount      = 0;
        foreach ($byStatus as $row) {
            $lower = strtolower(trim($row->label));
            if (in_array($lower, ['active', 'on going', 'ongoing'])) $activeCount   += (int) $row->count;
            if (str_contains($lower, 'existing'))                     $existingCount += (int) $row->count;
            if ($lower === 'raw')                                      $rawCount       = (int) $row->count;
        }

        $tasksDueToday = $tBase()->whereDate('todo_date', today())->count();

        // Top items
        $topAgent    = $isAdmin ? $byUser->first() : null;
        $topIndustry = $byIndustry->first();
        $topProduct  = $byCategory->first();

        return response()->json([
            'is_admin'        => $isAdmin,
            'total_contacts'  => $totalContacts,
            'this_month'      => $thisMonth,
            'last_month'      => $lastMonth,
            'unassigned'      => $unassigned,
            'active_count'    => $activeCount,
            'existing_count'  => $existingCount,
            'raw_count'       => $rawCount,
            'tasks_due_today' => $tasksDueToday,
            'top_agent'       => $topAgent,
            'top_industry'    => $topIndustry,
            'top_product'     => $topProduct,
            'by_status'       => $byStatus,
            'by_industry'     => $byIndustry,
            'by_category'     => $byCategory,
            'by_user'         => $byUser,
            'by_type'         => $byType,
            'by_month'        => $byMonth,
            'by_tasks'        => $byTasks,
        ]);
    }
}
