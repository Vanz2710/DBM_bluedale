<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PostingCalendarReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PostingCalendarController extends Controller
{
    public function index(Request $request)
    {
        $uid = $request->user()->id;

        $query = PostingCalendarReminder::where('user_id', $uid)
            ->orderBy('date')
            ->orderBy('time');

        if ($request->filled('month')) {
            $query->where('date', 'like', $request->month . '%');
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('client', 'like', $term);
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'platform' => 'required|in:FB,IG,TikTok,LinkedIn,Website',
            'client'   => 'nullable|string|max:255',
            'date'     => 'required|date_format:Y-m-d',
            'time'     => 'nullable|date_format:H:i',
            'status'   => 'required|in:planned,design,approval,scheduled,posted',
        ]);

        $userId   = $request->user()->id;
        $reminder = PostingCalendarReminder::create(['user_id' => $userId, ...$data]);

        Cache::forget("reminders_posting_{$userId}");
        return response()->json($reminder, 201);
    }

    public function update(Request $request, PostingCalendarReminder $postingCalendarReminder)
    {
        if ($postingCalendarReminder->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title'    => 'sometimes|string|max:255',
            'platform' => 'sometimes|in:FB,IG,TikTok,LinkedIn,Website',
            'client'   => 'nullable|string|max:255',
            'date'     => 'sometimes|date_format:Y-m-d',
            'time'     => 'nullable|date_format:H:i',
            'status'   => 'sometimes|in:planned,design,approval,scheduled,posted',
        ]);

        $postingCalendarReminder->update($data);
        Cache::forget("reminders_posting_{$request->user()->id}");

        return response()->json($postingCalendarReminder);
    }

    public function destroy(Request $request, PostingCalendarReminder $postingCalendarReminder)
    {
        if ($postingCalendarReminder->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $postingCalendarReminder->delete();
        Cache::forget("reminders_posting_{$request->user()->id}");
        return response()->json(null, 204);
    }
}
