<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactIncharge;
use App\Models\ToDo;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function summary()
    {
        $totalContacts  = Contact::count();
        $totalPics      = ContactIncharge::count();

        // This month vs last month
        $thisMonth = Contact::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)->count();
        $lastMonth = Contact::whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)->count();

        // Status distribution
        $byStatus = Contact::join('contact_statuses', 'contacts.status_id', '=', 'contact_statuses.id')
            ->select('contact_statuses.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_statuses.name')->orderByDesc('count')->get();

        // Industry distribution
        $byIndustry = Contact::join('contact_industries', 'contacts.industry_id', '=', 'contact_industries.id')
            ->select('contact_industries.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_industries.name')->orderByDesc('count')->get();

        // Category/product distribution
        $byCategory = Contact::join('contact_categories', 'contacts.category_id', '=', 'contact_categories.id')
            ->select('contact_categories.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_categories.name')->orderByDesc('count')->get();

        // User distribution
        $byUser = Contact::join('users', 'contacts.user_id', '=', 'users.id')
            ->select('users.name as label', DB::raw('count(*) as count'))
            ->groupBy('users.name')->orderByDesc('count')->get();

        // Type distribution
        $byType = Contact::join('contact_types', 'contacts.type_id', '=', 'contact_types.id')
            ->select('contact_types.name as label', DB::raw('count(*) as count'))
            ->groupBy('contact_types.name')->orderByDesc('count')->get();

        // Monthly records (last 12 months)
        $byMonth = Contact::select(
            DB::raw("DATE_FORMAT(created_at, '%b %Y') as label"),
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as sort_key"),
            DB::raw('count(*) as count')
        )->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
         ->groupBy('label', 'sort_key')
         ->orderBy('sort_key')
         ->get();

        // Unassigned
        $unassigned = Contact::whereNull('user_id')->count();

        // At-a-glance — derived from the already-fetched $byStatus collection
        $activeCount   = 0;
        $existingCount = 0;
        $rawCount      = 0;
        foreach ($byStatus as $row) {
            $lower = strtolower(trim($row->label));
            if (in_array($lower, ['active', 'on going', 'ongoing'])) $activeCount   += (int)$row->count;
            if (str_contains($lower, 'existing'))                     $existingCount += (int)$row->count;
            if ($lower === 'raw')                                      $rawCount       = (int)$row->count;
        }

        $tasksDueToday = ToDo::whereDate('todo_date', today())->count();

        // Top agent / industry / product
        $topAgent    = $byUser->first();
        $topIndustry = $byIndustry->first();
        $topProduct  = $byCategory->first();

        return response()->json([
            'total_contacts'  => $totalContacts,
            'total_pics'      => $totalPics,
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
        ]);
    }
}
