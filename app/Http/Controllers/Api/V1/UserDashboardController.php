<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['layout' => $request->user()->dashboard_layout]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layout'           => 'required|array',
            'layout.*.i'       => 'required|string|max:64',
            'layout.*.x'       => 'required|integer|min:0|max:11',
            'layout.*.y'       => 'required|integer|min:0',
            'layout.*.w'       => 'required|integer|min:1|max:12',
            'layout.*.h'       => 'required|integer|min:1|max:50',
            'layout.*.type'    => 'required|string|max:64',
        ]);

        $request->user()->update(['dashboard_layout' => $validated['layout']]);

        return response()->json(['layout' => $request->user()->dashboard_layout]);
    }
}
