<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactIncharge;
use App\Models\ToDo;
use App\Models\FollowUp;
use Illuminate\Support\Facades\DB;

class DataHealthController extends Controller
{
    public function index()
    {
        $total = Contact::count();

        // Missing fields
        $missing = [
            'no_name'     => Contact::whereNull('name')->orWhere('name', '')->count(),
            'no_user'     => Contact::whereNull('user_id')->count(),
            'no_status'   => Contact::whereNull('status_id')->count(),
            'no_type'     => Contact::whereNull('type_id')->count(),
            'no_industry' => Contact::whereNull('industry_id')->count(),
            'no_category' => Contact::whereNull('category_id')->count(),
        ];

        // Contacts with no PICs
        $noPic = Contact::doesntHave('incharges')->count();

        // PICs missing email/phone
        $picNoEmail = ContactIncharge::where(function ($q) {
            $q->whereNull('email')->orWhere('email', '');
        })->count();

        $picNoPhone = ContactIncharge::where(function ($q) {
            $q->whereNull('phone_mobile')->orWhere('phone_mobile', '');
        })->count();

        // Duplicate names
        $duplicates = Contact::select('name', DB::raw('count(*) as cnt'))
            ->whereNotNull('name')->where('name', '!=', '')
            ->groupBy('name')->having('cnt', '>', 1)
            ->orderByDesc('cnt')->limit(20)->get();

        // Overdue todos (past date, no follow-up)
        $overdueTodos = ToDo::where('todo_date', '<', today())
            ->doesntHave('followUps')->count();

        // Overdue follow-ups
        $overdueFollowups = FollowUp::where('followup_date', '<', today())->count();

        // Totals
        $totalTodos    = ToDo::count();
        $totalFollowups = FollowUp::count();
        $totalPics     = ContactIncharge::count();

        // Distributions
        $byStatus = Contact::join('contact_statuses', 'contacts.status_id', '=', 'contact_statuses.id')
            ->select('contact_statuses.name', DB::raw('count(*) as cnt'))
            ->groupBy('contact_statuses.name')->orderByDesc('cnt')->get();

        $byIndustry = Contact::join('contact_industries', 'contacts.industry_id', '=', 'contact_industries.id')
            ->select('contact_industries.name', DB::raw('count(*) as cnt'))
            ->groupBy('contact_industries.name')->orderByDesc('cnt')->limit(15)->get();

        $byUser = Contact::join('users', 'contacts.user_id', '=', 'users.id')
            ->select('users.name', DB::raw('count(*) as cnt'))
            ->groupBy('users.name')->orderByDesc('cnt')->get();

        // Health score
        $issues = 0;
        foreach ($missing as $v) if ($v > 0) $issues++;
        if ($noPic > 0)       $issues++;
        if ($picNoEmail > 0)  $issues++;
        if ($picNoPhone > 0)  $issues++;
        if ($duplicates->count() > 0) $issues++;
        if ($overdueTodos > 0) $issues++;

        $healthScore = $total > 0 ? max(0, 100 - ($issues * 9)) : 100;

        return response()->json([
            'total'            => $total,
            'total_pics'       => $totalPics,
            'total_todos'      => $totalTodos,
            'total_followups'  => $totalFollowups,
            'missing'          => $missing,
            'no_pic'           => $noPic,
            'pic_no_email'     => $picNoEmail,
            'pic_no_phone'     => $picNoPhone,
            'duplicates'       => $duplicates,
            'overdue_todos'    => $overdueTodos,
            'overdue_followups'=> $overdueFollowups,
            'by_status'        => $byStatus,
            'by_industry'      => $byIndustry,
            'by_user'          => $byUser,
            'health_score'     => $healthScore,
        ]);
    }
}
