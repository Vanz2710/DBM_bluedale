<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AdvertisingProductBooking;
use App\Models\DeptNotification;
use App\Models\FollowUp;
use App\Models\PostingCalendarReminder;
use App\Models\ReminderRead;
use App\Models\SystemAlert;
use App\Models\ToDo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);
        $userId  = $request->input('user_id');

        $today     = now()->toDateString();
        $pastFrom  = now()->subDays(14)->toDateString();
        $futureEnd = now()->addDays(7)->toDateString();

        // Admin with user_id → that user's data; otherwise always current user's
        $targetUserId = ($isAdmin && $userId) ? (int) $userId : $user->id;

        $todos = Cache::remember("reminders_todos_{$targetUserId}", 30, fn () => ToDo::select(
            'to_dos.id', 'to_dos.contact_id', 'to_dos.user_id',
            'to_dos.todo_date', 'to_dos.todo_remark',
            'contacts.name as contact_name'
        )
            ->join('contacts', 'to_dos.contact_id', '=', 'contacts.id')
            ->where('to_dos.user_id', $targetUserId)
            ->where('to_dos.completion_status', 'pending')
            ->whereBetween('to_dos.todo_date', [$pastFrom, $futureEnd])
            ->orderBy('to_dos.todo_date')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'contact_id'   => $t->contact_id,
                'todo_date'    => $t->todo_date->format('Y-m-d'),
                'todo_remark'  => $t->todo_remark,
                'contact_name' => $t->contact_name,
            ])
            ->all()
        );

        $followUps = Cache::remember("reminders_followups_{$targetUserId}", 30, fn () => FollowUp::select(
            'follow_ups.id', 'follow_ups.todo_id',
            'follow_ups.followup_date', 'follow_ups.action_type',
            'to_dos.contact_id', 'contacts.name as contact_name'
        )
            ->join('to_dos', 'follow_ups.todo_id', '=', 'to_dos.id')
            ->join('contacts', 'to_dos.contact_id', '=', 'contacts.id')
            ->where('to_dos.user_id', $targetUserId)
            ->where('follow_ups.completion_status', 'pending')
            ->whereBetween('follow_ups.followup_date', [$pastFrom, $futureEnd])
            ->orderBy('follow_ups.followup_date')
            ->get()
            ->map(fn($f) => [
                'id'           => $f->id,
                'contact_id'   => $f->contact_id,
                'followup_date' => $f->followup_date->format('Y-m-d'),
                'action_type'  => $f->action_type,
                'contact_name' => $f->contact_name,
            ])
            ->all()
        );

        $readTodoIds = array_flip(
            ReminderRead::where('user_id', $user->id)
                ->where('source_type', 'todo')
                ->pluck('source_id')
                ->toArray()
        );

        $readFollowUpIds = array_flip(
            ReminderRead::where('user_id', $user->id)
                ->where('source_type', 'followup')
                ->pluck('source_id')
                ->toArray()
        );

        $items = collect();

        foreach ($todos as $t) {
            $items->push([
                'id'           => $t['id'],
                'source_type'  => 'todo',
                'title'        => $t['todo_remark'] ?: 'To Do',
                'contact_name' => $t['contact_name'],
                'contact_id'   => $t['contact_id'],
                'due_date'     => $t['todo_date'],
                'link'         => '/todos/' . $t['id'],
                'is_read'      => isset($readTodoIds[$t['id']]),
            ]);
        }

        foreach ($followUps as $f) {
            $items->push([
                'id'           => $f['id'],
                'source_type'  => 'followup',
                'title'        => $f['action_type'] ?: 'Follow-Up',
                'contact_name' => $f['contact_name'],
                'contact_id'   => $f['contact_id'],
                'due_date'     => $f['followup_date'],
                'link'         => '/followups/' . $f['id'] . '/edit',
                'is_read'      => isset($readFollowUpIds[$f['id']]),
            ]);
        }

        $overdue  = $items->filter(fn($i) => $i['due_date'] < $today)->sortBy('due_date')->values();
        $dueToday = $items->filter(fn($i) => $i['due_date'] === $today)->sortBy('source_type')->values();
        $upcoming = $items->filter(fn($i) => $i['due_date'] > $today)->sortBy('due_date')->values();

        // Expiring site bookings — end within the next 30 days (visible to all users)
        $expiringSites = Cache::remember('reminders_expiring_sites', 30, fn () =>
            AdvertisingProductBooking::with('product:id,site_name,product_type')
                ->where('end_date', '>=', now()->toDateString())
                ->where('end_date', '<=', now()->addDays(30)->toDateString())
                ->orderBy('end_date')
                ->get()
                ->map(fn ($b) => [
                    'id'           => $b->id,
                    'source_type'  => 'site_expiry',
                    'company_name' => $b->company_name,
                    'site_name'    => $b->product->site_name ?? 'Unknown Site',
                    'product_type' => $b->product->product_type ?? '',
                    'end_date'     => $b->end_date->format('Y-m-d'),
                    'days_left'    => (int) $b->end_date->diffInDays(now()),
                ])
                ->all()
        );

        $postingReminders = [];
        if ($user->can('manage posting-calendar')) {
            $postingReminders = Cache::remember("reminders_posting_{$targetUserId}", 30, fn () =>
                PostingCalendarReminder::where('user_id', $targetUserId)
                    ->where('status', '!=', 'posted')
                    ->whereBetween('date', [$pastFrom, $futureEnd])
                    ->orderBy('date')
                    ->orderBy('time')
                    ->get()
                    ->map(fn ($r) => [
                        'id'          => $r->id,
                        'source_type' => 'posting',
                        'title'       => $r->title,
                        'platform'    => $r->platform,
                        'date'        => $r->date,
                        'status'      => $r->status,
                    ])
                    ->all()
            );
        }

        $alerts = [];
        if ($isAdmin) {
            $alerts = SystemAlert::where('for_user_id', $user->id)
                ->whereNull('read_at')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn($a) => [
                    'id'          => $a->id,
                    'source_type' => 'alert',
                    'type'        => $a->type,
                    'title'       => $a->title,
                    'body'        => $a->body,
                    'link'        => $a->link,
                    'is_read'     => false,
                    'created_at'  => $a->created_at->format('d M Y H:i'),
                ])
                ->values()
                ->all();
        }

        $taskNotifs = DeptNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->with('task:id,title')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($n) => [
                'id'          => $n->id,
                'source_type' => 'task_notification',
                'type'        => $n->type,
                'message'     => $n->message,
                'task_id'     => $n->task_id,
                'task_title'  => $n->task?->title,
                'is_read'     => false,
                'created_at'  => $n->created_at->format('d M Y, H:i'),
            ])
            ->values()
            ->all();

        $readAnnouncementIds = AnnouncementRead::where('user_id', $user->id)->pluck('announcement_id')->flip()->all();
        $announcements = Announcement::active()
            ->where(fn($q) => $q->whereNull('target_user_id')->orWhere('target_user_id', $user->id))
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'source_type' => 'announcement',
                'urgency'     => $a->urgency ?? 'normal',
                'title'       => $a->title,
                'body'        => $a->body,
                'author'      => $a->author?->name,
                'is_read'     => isset($readAnnouncementIds[$a->id]),
                'created_at'  => $a->published_at->format('d M Y, H:i'),
            ])
            ->values()
            ->all();

        $unreadAnnouncements = collect($announcements)->where('is_read', false)->count();

        return response()->json([
            'overdue'             => $overdue,
            'today'               => $dueToday,
            'upcoming'            => $upcoming,
            'alerts'              => $alerts,
            'expiring_sites'      => $expiringSites,
            'posting_reminders'   => $postingReminders,
            'task_notifications'  => $taskNotifs,
            'announcements'       => $announcements,
            'unread_count'        => $items->where('is_read', false)->count() + count($alerts) + count($expiringSites) + count($postingReminders) + count($taskNotifs) + $unreadAnnouncements,
        ]);
    }

    public function markRead(Request $request)
    {
        $userId = Auth::id();
        $items  = $request->input('items', []);

        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            $id   = (int) ($item['id'] ?? 0);
            if ($id <= 0) continue;

            if ($type === 'alert') {
                SystemAlert::where('id', $id)
                    ->where('for_user_id', $userId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
                continue;
            }

            if ($type === 'task_notification') {
                DeptNotification::where('id', $id)
                    ->where('user_id', $userId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
                continue;
            }

            if ($type === 'announcement') {
                AnnouncementRead::firstOrCreate(
                    ['announcement_id' => $id, 'user_id' => $userId],
                    ['read_at' => now()]
                );
                continue;
            }

            if (!in_array($type, ['todo', 'followup'])) continue;

            ReminderRead::firstOrCreate(
                ['user_id' => $userId, 'source_type' => $type, 'source_id' => $id],
                ['read_at' => now()]
            );
        }

        return response()->json(['status' => 'success']);
    }
}
