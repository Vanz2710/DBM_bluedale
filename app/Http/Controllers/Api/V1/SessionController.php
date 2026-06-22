<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'last_used_at', 'created_at'])
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'last_used_at' => $t->last_used_at,
                'created_at'   => $t->created_at,
                'is_current'   => $t->id === $currentId,
            ]);

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, int $id)
    {
        $currentId = $request->user()->currentAccessToken()->id;

        if ($id === $currentId) {
            return response()->json(['message' => 'Use the logout button to end your current session.'], 422);
        }

        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json(['status' => 'success']);
    }

    public function destroyAll(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()->id;
        $request->user()->tokens()->where('id', '!=', $currentId)->delete();
        return response()->json(['status' => 'success']);
    }
}
