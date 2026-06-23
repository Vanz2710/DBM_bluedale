<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    // All authenticated users — returns active announcements visible to this user + read state
    public function index()
    {
        $userId = Auth::id();

        $announcements = Announcement::active()
            ->where(fn($q) => $q->whereNull('target_user_id')->orWhere('target_user_id', $userId))
            ->with('author:id,name')
            ->withCount(['reads as is_read' => fn($q) => $q->where('user_id', $userId)])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'urgency'      => $a->urgency ?? 'normal',
                'published_at' => $a->published_at->format('d M Y, H:i'),
                'expires_at'   => $a->expires_at?->format('d M Y'),
                'author'       => $a->author?->name,
                'is_read'      => (bool) $a->is_read,
            ]);

        return response()->json(['data' => $announcements]);
    }

    // Admin only — full list including drafts
    public function adminIndex()
    {
        $this->requireAdmin();

        $announcements = Announcement::with('author:id,name', 'targetUser:id,name')
            ->withCount('reads')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'title'          => $a->title,
                'body'           => $a->body,
                'urgency'        => $a->urgency ?? 'normal',
                'target_user_id' => $a->target_user_id,
                'target_user'    => $a->targetUser?->name,
                'published_at'   => $a->published_at?->format('Y-m-d\TH:i'),
                'expires_at'     => $a->expires_at?->format('Y-m-d'),
                'author'         => $a->author?->name,
                'reads_count'    => $a->reads_count,
                'is_draft'       => is_null($a->published_at) || $a->published_at > now(),
            ]);

        return response()->json(['data' => $announcements]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'title'          => 'required|string|max:200',
            'body'           => 'required|string',
            'urgency'        => 'nullable|in:normal,urgent',
            'target_user_id' => 'nullable|exists:users,id',
            'published_at'   => 'nullable|date',
            'expires_at'     => 'nullable|date|after:published_at',
        ]);

        $announcement = Announcement::create([
            'created_by'     => Auth::id(),
            'title'          => $validated['title'],
            'body'           => $validated['body'],
            'urgency'        => $validated['urgency'] ?? 'normal',
            'target_user_id' => $validated['target_user_id'] ?? null,
            'published_at'   => $validated['published_at'] ?? now(),
            'expires_at'     => $validated['expires_at'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'data' => $announcement], 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'title'          => 'required|string|max:200',
            'body'           => 'required|string',
            'urgency'        => 'nullable|in:normal,urgent',
            'target_user_id' => 'nullable|exists:users,id',
            'published_at'   => 'nullable|date',
            'expires_at'     => 'nullable|date',
        ]);

        $announcement->update([
            'title'          => $validated['title'],
            'body'           => $validated['body'],
            'urgency'        => $validated['urgency'] ?? 'normal',
            'target_user_id' => $validated['target_user_id'] ?? null,
            'published_at'   => $validated['published_at'],
            'expires_at'     => $validated['expires_at'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'data' => $announcement]);
    }

    public function destroy(Announcement $announcement)
    {
        $this->requireAdmin();
        $announcement->delete();
        return response()->json(['status' => 'success']);
    }

    // Mark one announcement as read for current user
    public function markRead(Announcement $announcement)
    {
        AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcement->id,
            'user_id'         => Auth::id(),
        ], ['read_at' => now()]);

        return response()->json(['status' => 'success']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }
    }
}
