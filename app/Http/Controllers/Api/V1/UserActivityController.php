<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUp;
use App\Models\Project;
use App\Models\SystemAlert;
use App\Models\ToDo;
use App\Models\User;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    public function overview(Request $request)
    {
        $period = max(1, (int) $request->input('period', 30));
        $since  = now()->subDays($period);

        $users = User::with('roles:id,name')
            ->get(['id', 'name', 'username', 'email', 'last_login_at', 'login_count',
                   'inactivity_flagged_at', 'is_approved', 'created_at']);

        $contactCounts  = Contact::selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $todosCounts = ToDo::selectRaw('user_id, COUNT(*) as cnt')
            ->where('completion_status', 'completed')
            ->where('completed_at', '>=', $since)
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $followupCounts = FollowUp::selectRaw('to_dos.user_id, COUNT(follow_ups.id) as cnt')
            ->join('to_dos', 'follow_ups.todo_id', '=', 'to_dos.id')
            ->where('follow_ups.completion_status', 'completed')
            ->where('follow_ups.completed_at', '>=', $since)
            ->groupBy('to_dos.user_id')->pluck('cnt', 'to_dos.user_id');

        $dealCounts = Deal::selectRaw('user_id, COUNT(*) as cnt')
            ->where('created_at', '>=', $since)
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $projectCounts = Project::selectRaw('user_id, COUNT(*) as cnt')
            ->where('created_at', '>=', $since)
            ->groupBy('user_id')->pluck('cnt', 'user_id');

        $data = $users->map(function (User $user) use (
            $contactCounts, $todosCounts, $followupCounts, $dealCounts, $projectCounts
        ) {
            $daysSince = $user->last_login_at
                ? (int) now()->diffInDays($user->last_login_at)
                : null;

            if ($user->inactivity_flagged_at) {
                $status = 'locked';
            } elseif (is_null($user->last_login_at)) {
                $status = 'never_logged_in';
            } elseif ($daysSince >= 30) {
                $status = 'dormant';
            } elseif ($daysSince >= 14) {
                $status = 'at_risk';
            } else {
                $status = 'active';
            }

            return [
                'id'                  => $user->id,
                'name'                => $user->name,
                'username'            => $user->username,
                'roles'               => $user->roles->pluck('name'),
                'last_login_at'       => $user->last_login_at?->format('d M Y H:i'),
                'login_count'         => $user->login_count,
                'days_since_login'    => $daysSince,
                'status'              => $status,
                'member_since'        => $user->created_at->format('d M Y'),
                'contacts'            => $contactCounts[$user->id] ?? 0,
                'todos_completed'     => $todosCounts[$user->id] ?? 0,
                'followups_completed' => $followupCounts[$user->id] ?? 0,
                'deals_created'       => $dealCounts[$user->id] ?? 0,
                'projects_created'    => $projectCounts[$user->id] ?? 0,
            ];
        })->values();

        return response()->json(['data' => $data, 'period' => $period]);
    }

    public function securityEvents(Request $request)
    {
        $passwordChanges = SystemAlert::where('type', 'password_change')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($a) => [
                'event_type' => 'password_change',
                'label'      => 'Password Changed',
                'title'      => $a->title,
                'body'       => $a->body,
                'actor'      => null,
                'ts'         => $a->created_at->timestamp,
                'created_at' => $a->created_at->format('d M Y H:i'),
            ]);

        $auditEvents = AdminAuditLog::with('actor:id,name')
            ->whereIn('action', ['restored_access', 'approved', 'created', 'deleted', 'restored'])
            ->where('entity_type', 'user')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn($log) => [
                'event_type' => $log->action,
                'label'      => match ($log->action) {
                    'restored_access' => 'Access Restored',
                    'approved'        => 'User Approved',
                    'created'         => 'User Created',
                    'deleted'         => 'User Deleted',
                    'restored'        => 'User Restored',
                    default           => ucfirst($log->action),
                },
                'title'      => $log->entity_name ?? 'Unknown',
                'body'       => $log->actor ? 'By ' . $log->actor->name : 'By system',
                'actor'      => $log->actor?->name,
                'ts'         => $log->created_at->timestamp,
                'created_at' => $log->created_at->format('d M Y H:i'),
            ]);

        $events = $passwordChanges->concat($auditEvents)
            ->sortByDesc('ts')
            ->values();

        return response()->json(['events' => $events]);
    }
}
