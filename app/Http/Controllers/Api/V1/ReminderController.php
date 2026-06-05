<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
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
            ->whereBetween('to_dos.todo_date', [$pastFrom, $futureEnd])
            ->orderBy('to_dos.todo_date')
            ->get()
        );

        $followUps = Cache::remember("reminders_followups_{$targetUserId}", 30, fn () => FollowUp::select(
            'follow_ups.id', 'follow_ups.todo_id',
            'follow_ups.followup_date', 'follow_ups.action_type',
            'to_dos.contact_id', 'contacts.name as contact_name'
        )
            ->join('to_dos', 'follow_ups.todo_id', '=', 'to_dos.id')
            ->join('contacts', 'to_dos.contact_id', '=', 'contacts.id')
            ->where('to_dos.user_id', $targetUserId)
            ->whereBetween('follow_ups.followup_date', [$pastFrom, $futureEnd])
            ->orderBy('follow_ups.followup_date')
            ->get()
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
                'id'           => $t->id,
                'source_type'  => 'todo',
                'title'        => $t->todo_remark ?: 'To Do',
                'contact_name' => $t->contact_name,
                'contact_id'   => $t->contact_id,
                'due_date'     => $t->todo_date->format('Y-m-d'),
                'link'         => '/todos/' . $t->id . '/edit',
                'is_read'      => isset($readTodoIds[$t->id]),
            ]);
        }

        foreach ($followUps as $f) {
            $items->push([
                'id'           => $f->id,
                'source_type'  => 'followup',
                'title'        => $f->action_type ?: 'Follow-Up',
                'contact_name' => $f->contact_name,
                'contact_id'   => $f->contact_id,
                'due_date'     => $f->followup_date->format('Y-m-d'),
                'link'         => '/followups/' . $f->id . '/edit',
                'is_read'      => isset($readFollowUpIds[$f->id]),
            ]);
        }

        $overdue  = $items->filter(fn($i) => $i['due_date'] < $today)->sortBy('due_date')->values();
        $dueToday = $items->filter(fn($i) => $i['due_date'] === $today)->sortBy('source_type')->values();
        $upcoming = $items->filter(fn($i) => $i['due_date'] > $today)->sortBy('due_date')->values();

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

        return response()->json([
            'overdue'      => $overdue,
            'today'        => $dueToday,
            'upcoming'     => $upcoming,
            'alerts'       => $alerts,
            'unread_count' => $items->where('is_read', false)->count() + count($alerts),
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

            if (!in_array($type, ['todo', 'followup'])) continue;

            ReminderRead::firstOrCreate(
                ['user_id' => $userId, 'source_type' => $type, 'source_id' => $id],
                ['read_at' => now()]
            );
        }

        return response()->json(['status' => 'success']);
    }
}
